<?php

declare(strict_types=1);

namespace TerminalRoguelike\Services;

use TerminalRoguelike\Exceptions\GameException;
use TerminalRoguelike\Exceptions\InvalidActionException;
use TerminalRoguelike\Models\Enemy;
use TerminalRoguelike\Models\Item;
use TerminalRoguelike\Models\Player;
use TerminalRoguelike\Models\Room;
use TerminalRoguelike\Models\Run;
use TerminalRoguelike\Repositories\EnemyRepository;
use TerminalRoguelike\Repositories\GameLogRepository;
use TerminalRoguelike\Repositories\ItemRepository;
use TerminalRoguelike\Repositories\PlayerRepository;
use TerminalRoguelike\Repositories\RoomRepository;
use TerminalRoguelike\Repositories\RunRepository;

/**
 * Orchestrates a game run: ties the dungeon generator, combat and experience
 * services together with the repositories and exposes a single entry point
 * (performAction) that both the text-command and numbered-option inputs
 * funnel through, so both input modes share identical backend logic.
 */
class GameService
{
    private const MAX_FLOORS = 5;

    private const DIRECTIONS = ['north', 'south', 'east', 'west'];

    private const DIRECTION_LABELS_RU = [
        'north' => 'на север',
        'south' => 'на юг',
        'east' => 'на восток',
        'west' => 'на запад',
    ];

    private const ROOM_DESCRIPTIONS = [
        Room::TYPE_ENTRANCE => 'Ты стоишь у входа в подземный сервер-зал. Гул охлаждения заполняет темноту.',
        Room::TYPE_TREASURE => 'В углу комнаты мерцает забытый файл с полезным грузом.',
        Room::TYPE_TRAP => 'Пол комнаты испещрён подозрительными проводами...',
        Room::TYPE_REST => 'Тихая комната с резервным питанием — здесь можно перевести дух.',
        Room::TYPE_STAIRS => 'Ты видишь люк, ведущий на нижний уровень системы.',
        Room::TYPE_EMPTY => 'Пустая, гулкая серверная комната.',
    ];

    public function __construct(
        private RunRepository $runRepository,
        private PlayerRepository $playerRepository,
        private RoomRepository $roomRepository,
        private EnemyRepository $enemyRepository,
        private ItemRepository $itemRepository,
        private GameLogRepository $gameLogRepository,
        private DungeonGeneratorService $dungeonGenerator,
        private CombatService $combatService,
        private ExperienceService $experienceService
    ) {
    }

    public function startRun(string $playerName): array
    {
        $playerName = trim($playerName) !== '' ? trim($playerName) : 'Аноним';
        $token = bin2hex(random_bytes(16));

        $run = $this->runRepository->create($playerName, $token);
        $player = $this->playerRepository->create(Player::createDefault($run->id));

        $rooms = $this->dungeonGenerator->generateFloor($run->id, 1);
        $entrance = $this->findRoomByType($rooms, Room::TYPE_ENTRANCE);

        $player->currentRoomId = $entrance->id;
        $entrance->visited = true;
        $this->roomRepository->save($entrance);
        $this->playerRepository->save($player);

        $this->gameLogRepository->append($run->id, "Забег начат. Удачи, {$playerName}.");
        $this->gameLogRepository->append($run->id, self::ROOM_DESCRIPTIONS[Room::TYPE_ENTRANCE]);

        return $this->buildState($run, $player);
    }

    public function getState(string $token): array
    {
        [$run, $player] = $this->loadRunAndPlayer($token);

        return $this->buildState($run, $player);
    }

    public function performAction(string $token, string $rawCommand): array
    {
        [$run, $player] = $this->loadRunAndPlayer($token);

        if (!$run->isActive()) {
            throw new InvalidActionException('Этот забег уже завершён.');
        }

        $room = $this->requireRoom((int) $player->currentRoomId);
        $enemy = $this->enemyRepository->findByRoomId((int) $room->id);
        $roomItem = $this->itemRepository->findUntakenByRoomId((int) $room->id);

        $command = $this->parseCommand($rawCommand, $this->buildOptions($run, $player, $room, $enemy, $roomItem));

        $this->executeCommand($command, $run, $player, $room, $enemy, $roomItem);

        return $this->buildState($run, $player);
    }

    public function leaderboard(int $limit = 10): array
    {
        return array_map(static fn (Run $run) => $run->toArray(), $this->runRepository->findTopRuns($limit));
    }

    // ------------------------------------------------------------------
    // Command execution
    // ------------------------------------------------------------------

    private function executeCommand(
        string $command,
        Run $run,
        Player $player,
        Room $room,
        ?Enemy $enemy,
        ?Item $roomItem
    ): void {
        switch (true) {
            case $command === 'look':
                $this->gameLogRepository->append($run->id, $this->describeRoom($room, $enemy, $roomItem));
                return;

            case $command === 'inventory':
                $this->gameLogRepository->append($run->id, $this->describeInventory((int) $player->id));
                return;

            case $command === 'attack':
                $this->handleAttack($run, $player, $room, $enemy);
                return;

            case $command === 'use potion':
                $this->handleUsePotion($run, $player, $enemy);
                return;

            case $command === 'take':
                $this->handleTake($run, $player, $room, $roomItem);
                return;

            case $command === 'rest':
                $this->handleRest($run, $player, $room);
                return;

            case $command === 'go down':
                $this->handleDescend($run, $player, $room);
                return;

            case str_starts_with($command, 'go '):
                $this->handleMove($run, $player, $room, $enemy, substr($command, 3));
                return;

            default:
                throw new InvalidActionException("Неизвестная команда: {$command}");
        }
    }

    private function handleMove(Run $run, Player $player, Room $room, ?Enemy $enemy, string $direction): void
    {
        if ($enemy !== null && $enemy->alive) {
            throw new InvalidActionException('Нельзя уйти — враг преграждает путь!');
        }

        if (!isset($room->connections[$direction])) {
            throw new InvalidActionException('Там глухая стена.');
        }

        $targetRoom = $this->requireRoom($room->connections[$direction]);
        $player->currentRoomId = $targetRoom->id;
        $this->playerRepository->save($player);

        $label = self::DIRECTION_LABELS_RU[$direction] ?? $direction;
        $this->gameLogRepository->append($run->id, "Ты идёшь {$label}.");

        $this->onEnterRoom($run, $player, $targetRoom);
    }

    private function onEnterRoom(Run $run, Player $player, Room $room): void
    {
        $wasVisited = $room->visited;
        $room->visited = true;
        $this->roomRepository->save($room);

        $enemy = $this->enemyRepository->findByRoomId((int) $room->id);
        $roomItem = $this->itemRepository->findUntakenByRoomId((int) $room->id);

        $this->gameLogRepository->append($run->id, $this->describeRoom($room, $enemy, $roomItem));

        if ($room->type === Room::TYPE_TRAP && !$wasVisited) {
            $this->triggerTrap($run, $player);
            return;
        }

        if ($enemy !== null && $enemy->alive) {
            $this->gameLogRepository->append($run->id, "На тебя набрасывается «{$enemy->name}»!");
        }
    }

    private function triggerTrap(Run $run, Player $player): void
    {
        $damage = random_int(5, 15);
        $player->hp = max(0, $player->hp - $damage);
        $this->playerRepository->save($player);

        $this->gameLogRepository->append($run->id, "Ловушка активирована! Ты получаешь {$damage} урона.");

        if ($this->combatService->isPlayerDead($player)) {
            $this->endRun($run, $player, Run::STATUS_DEAD);
        }
    }

    private function handleAttack(Run $run, Player $player, Room $room, ?Enemy $enemy): void
    {
        if ($enemy === null || !$enemy->alive) {
            throw new InvalidActionException('Здесь не с кем сражаться.');
        }

        $result = $this->combatService->playerAttack($player, $enemy);
        $this->enemyRepository->save($enemy);
        $this->gameLogRepository->append($run->id, $result['message']);

        if ($this->combatService->isEnemyDead($enemy)) {
            $this->resolveEnemyDeath($run, $player, $room, $enemy);
            return;
        }

        $this->resolveEnemyRetaliation($run, $player, $enemy);
    }

    private function handleUsePotion(Run $run, Player $player, ?Enemy $enemy): void
    {
        $potion = $this->itemRepository->findInventoryItemByType((int) $player->id, Item::TYPE_POTION);

        if ($potion === null) {
            throw new InvalidActionException('У тебя нет зелий.');
        }

        $healed = min($potion->effectValue, $player->maxHp - $player->hp);
        $player->hp = min($player->maxHp, $player->hp + $potion->effectValue);
        $this->playerRepository->save($player);
        $this->itemRepository->delete($potion);

        $this->gameLogRepository->append(
            $run->id,
            "Ты используешь «{$potion->name}» и восстанавливаешь {$healed} HP."
        );

        if ($enemy !== null && $enemy->alive) {
            $this->resolveEnemyRetaliation($run, $player, $enemy);
        }
    }

    private function handleTake(Run $run, Player $player, Room $room, ?Item $roomItem): void
    {
        if ($roomItem === null) {
            throw new InvalidActionException('Здесь нечего взять.');
        }

        $this->itemRepository->assignToPlayer($roomItem, (int) $player->id);
        $room->cleared = true;
        $this->roomRepository->save($room);

        $run->itemsFound++;
        $this->runRepository->save($run);

        $this->gameLogRepository->append($run->id, "Ты подбираешь: «{$roomItem->name}».");

        if (in_array($roomItem->type, [Item::TYPE_WEAPON, Item::TYPE_ARMOR], true)) {
            $this->equipItem($run, $player, $roomItem);
        }
    }

    private function equipItem(Run $run, Player $player, Item $item): void
    {
        if ($item->type === Item::TYPE_WEAPON) {
            $player->attack += $item->effectValue;
        } else {
            $player->defense += $item->effectValue;
        }

        $item->equipped = true;
        $this->playerRepository->save($player);

        $this->gameLogRepository->append($run->id, "Экипировано: «{$item->name}».");
    }

    private function handleRest(Run $run, Player $player, Room $room): void
    {
        if ($room->type !== Room::TYPE_REST) {
            throw new InvalidActionException('Здесь нельзя отдохнуть.');
        }

        $player->hp = $player->maxHp;
        $player->mp = $player->maxMp;
        $this->playerRepository->save($player);

        $this->gameLogRepository->append($run->id, 'Ты отдыхаешь у резервного питания и полностью восстанавливаешься.');
    }

    private function handleDescend(Run $run, Player $player, Room $room): void
    {
        if ($room->type !== Room::TYPE_STAIRS) {
            throw new InvalidActionException('Здесь нет лестницы вниз.');
        }

        if ($run->floor >= self::MAX_FLOORS) {
            $this->endRun($run, $player, Run::STATUS_WON);
            $this->gameLogRepository->append($run->id, 'Ты выбираешься из системы. Забег завершён победой!');
            return;
        }

        $run->floor++;
        $this->runRepository->save($run);

        $rooms = $this->dungeonGenerator->generateFloor($run->id, $run->floor);
        $entrance = $this->findRoomByType($rooms, Room::TYPE_ENTRANCE);
        $entrance->visited = true;
        $this->roomRepository->save($entrance);

        $player->currentRoomId = $entrance->id;
        $this->playerRepository->save($player);

        $this->gameLogRepository->append($run->id, "Ты спускаешься на этаж {$run->floor}.");
        $this->gameLogRepository->append($run->id, $this->describeRoom($entrance, null, null));
    }

    private function resolveEnemyDeath(Run $run, Player $player, Room $room, Enemy $enemy): void
    {
        $this->gameLogRepository->append($run->id, "«{$enemy->name}» повержен.");

        $run->enemiesKilled++;
        $room->cleared = true;
        $this->roomRepository->save($room);

        $expReward = $this->experienceService->expRewardFor($enemy);
        $levelUpMessages = $this->experienceService->addExp($player, $expReward);
        $this->playerRepository->save($player);
        $this->runRepository->save($run);

        $this->gameLogRepository->append($run->id, "Получено {$expReward} опыта.");
        foreach ($levelUpMessages as $message) {
            $this->gameLogRepository->append($run->id, $message);
        }
    }

    private function resolveEnemyRetaliation(Run $run, Player $player, Enemy $enemy): void
    {
        $result = $this->combatService->enemyAttack($enemy, $player);
        $this->playerRepository->save($player);
        $this->gameLogRepository->append($run->id, $result['message']);

        if ($this->combatService->isPlayerDead($player)) {
            $this->endRun($run, $player, Run::STATUS_DEAD);
        }
    }

    private function endRun(Run $run, Player $player, string $status): void
    {
        $run->status = $status;
        $run->endedAt = gmdate('Y-m-d H:i:s');
        $run->score = $this->calculateScore($run, $player);
        $this->runRepository->save($run);

        $message = $status === Run::STATUS_WON
            ? 'Забег завершён победой!'
            : 'Ты пал в цифровых глубинах. Забег окончен.';
        $this->gameLogRepository->append($run->id, $message);
    }

    private function calculateScore(Run $run, Player $player): int
    {
        return $run->floor * 100 + $run->enemiesKilled * 10 + $run->itemsFound * 5 + $player->level * 20;
    }

    // ------------------------------------------------------------------
    // Command parsing / options
    // ------------------------------------------------------------------

    /**
     * @param array<int, array{label:string, command:string}> $options
     */
    private function parseCommand(string $raw, array $options): string
    {
        $trimmed = trim(mb_strtolower($raw));

        if ($trimmed === '') {
            throw new InvalidActionException('Пустая команда.');
        }

        if (ctype_digit($trimmed)) {
            $index = ((int) $trimmed) - 1;
            if (!isset($options[$index])) {
                throw new InvalidActionException("Нет варианта под номером {$trimmed}.");
            }

            return $options[$index]['command'];
        }

        $synonyms = [
            'n' => 'go north', 'north' => 'go north', 'go north' => 'go north', 'идти на север' => 'go north',
            's' => 'go south', 'south' => 'go south', 'go south' => 'go south', 'идти на юг' => 'go south',
            'e' => 'go east', 'east' => 'go east', 'go east' => 'go east', 'идти на восток' => 'go east',
            'w' => 'go west', 'west' => 'go west', 'go west' => 'go west', 'идти на запад' => 'go west',
            'down' => 'go down', 'go down' => 'go down', 'stairs' => 'go down', 'вниз' => 'go down',
            'look' => 'look', 'l' => 'look', 'осмотреться' => 'look',
            'inventory' => 'inventory', 'inv' => 'inventory', 'i' => 'inventory', 'инвентарь' => 'inventory',
            'attack' => 'attack', 'a' => 'attack', 'fight' => 'attack', 'атаковать' => 'attack',
            'use potion' => 'use potion', 'use' => 'use potion', 'drink potion' => 'use potion', 'зелье' => 'use potion',
            'take' => 'take', 'get' => 'take', 'pick up' => 'take', 'take item' => 'take', 'взять' => 'take',
            'rest' => 'rest', 'отдохнуть' => 'rest',
        ];

        if (!isset($synonyms[$trimmed])) {
            throw new InvalidActionException("Неизвестная команда: «{$raw}».");
        }

        return $synonyms[$trimmed];
    }

    /**
     * Builds the numbered-option list shown to the player. Every option maps
     * to the exact same canonical command string the text parser produces,
     * so clicking option N and typing its command run identical logic.
     *
     * @return array<int, array{label:string, command:string}>
     */
    private function buildOptions(Run $run, Player $player, Room $room, ?Enemy $enemy, ?Item $roomItem): array
    {
        $options = [];

        if ($enemy !== null && $enemy->alive) {
            $options[] = ['label' => 'Атаковать', 'command' => 'attack'];
        } else {
            foreach (self::DIRECTIONS as $direction) {
                if (isset($room->connections[$direction])) {
                    $label = 'Идти ' . self::DIRECTION_LABELS_RU[$direction];
                    $options[] = ['label' => $label, 'command' => 'go ' . $direction];
                }
            }

            if ($room->type === Room::TYPE_STAIRS) {
                $options[] = ['label' => 'Спуститься по лестнице', 'command' => 'go down'];
            }

            if ($room->type === Room::TYPE_REST) {
                $options[] = ['label' => 'Отдохнуть', 'command' => 'rest'];
            }

            if ($roomItem !== null) {
                $options[] = ['label' => "Взять: {$roomItem->name}", 'command' => 'take'];
            }
        }

        if ($this->itemRepository->findInventoryItemByType((int) $player->id, Item::TYPE_POTION) !== null) {
            $options[] = ['label' => 'Использовать зелье', 'command' => 'use potion'];
        }

        $options[] = ['label' => 'Осмотреться', 'command' => 'look'];
        $options[] = ['label' => 'Инвентарь', 'command' => 'inventory'];

        return $options;
    }

    // ------------------------------------------------------------------
    // State / descriptions
    // ------------------------------------------------------------------

    private function buildState(Run $run, Player $player): array
    {
        $room = $this->requireRoom((int) $player->currentRoomId);
        $enemy = $this->enemyRepository->findByRoomId((int) $room->id);
        $roomItem = $this->itemRepository->findUntakenByRoomId((int) $room->id);
        $inventory = $this->itemRepository->findInventory((int) $player->id);

        return [
            'run' => $run->toArray(),
            'player' => $player->toArray(),
            'room' => $room->toArray(),
            'enemy' => $enemy && $enemy->alive ? $enemy->toArray() : null,
            'roomItem' => $roomItem?->toArray(),
            'inventory' => array_map(static fn (Item $item) => $item->toArray(), $inventory),
            'options' => $run->isActive() ? $this->buildOptions($run, $player, $room, $enemy, $roomItem) : [],
            'log' => $this->gameLogRepository->recent($run->id, 30),
        ];
    }

    private function describeRoom(Room $room, ?Enemy $enemy, ?Item $roomItem): string
    {
        $description = self::ROOM_DESCRIPTIONS[$room->type] ?? self::ROOM_DESCRIPTIONS[Room::TYPE_EMPTY];

        if ($enemy !== null && $enemy->alive) {
            $description .= " Здесь притаился враг: «{$enemy->name}» (HP {$enemy->hp}/{$enemy->maxHp}).";
        }

        if ($roomItem !== null) {
            $description .= " На полу лежит: «{$roomItem->name}».";
        }

        return $description;
    }

    private function describeInventory(int $playerId): string
    {
        $items = $this->itemRepository->findInventory($playerId);

        if (empty($items)) {
            return 'Инвентарь пуст.';
        }

        $names = array_map(static fn (Item $item) => $item->name . ($item->equipped ? ' (экипировано)' : ''), $items);

        return 'Инвентарь: ' . implode(', ', $names) . '.';
    }

    /**
     * @param Room[] $rooms
     */
    private function findRoomByType(array $rooms, string $type): Room
    {
        foreach ($rooms as $room) {
            if ($room->type === $type) {
                return $room;
            }
        }

        throw new GameException("Не удалось найти комнату типа {$type} на сгенерированном этаже.");
    }

    private function requireRoom(int $roomId): Room
    {
        $room = $this->roomRepository->findById($roomId);

        if ($room === null) {
            throw new GameException("Комната #{$roomId} не найдена.");
        }

        return $room;
    }

    /**
     * @return array{0: Run, 1: Player}
     */
    private function loadRunAndPlayer(string $token): array
    {
        $run = $this->runRepository->findByToken($token);
        if ($run === null) {
            throw new InvalidActionException('Забег не найден.');
        }

        $player = $this->playerRepository->findByRunId((int) $run->id);
        if ($player === null) {
            throw new GameException("У забега #{$run->id} нет игрока.");
        }

        return [$run, $player];
    }
}
