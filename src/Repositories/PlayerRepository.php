<?php

declare(strict_types=1);

namespace TerminalRoguelike\Repositories;

use PDO;
use TerminalRoguelike\Models\Player;

class PlayerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(Player $player): Player
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO players (run_id, level, hp, max_hp, mp, max_mp, attack, defense, exp, exp_to_next, gold, current_room_id)
             VALUES (:run_id, :level, :hp, :max_hp, :mp, :max_mp, :attack, :defense, :exp, :exp_to_next, :gold, :current_room_id)'
        );
        $statement->execute([
            'run_id' => $player->runId,
            'level' => $player->level,
            'hp' => $player->hp,
            'max_hp' => $player->maxHp,
            'mp' => $player->mp,
            'max_mp' => $player->maxMp,
            'attack' => $player->attack,
            'defense' => $player->defense,
            'exp' => $player->exp,
            'exp_to_next' => $player->expToNext,
            'gold' => $player->gold,
            'current_room_id' => $player->currentRoomId,
        ]);

        $player->id = (int) $this->pdo->lastInsertId();

        return $player;
    }

    public function findByRunId(int $runId): ?Player
    {
        $statement = $this->pdo->prepare('SELECT * FROM players WHERE run_id = :run_id');
        $statement->execute(['run_id' => $runId]);
        $row = $statement->fetch();

        return $row ? Player::fromRow($row) : null;
    }

    public function save(Player $player): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE players SET level = :level, hp = :hp, max_hp = :max_hp, mp = :mp, max_mp = :max_mp,
             attack = :attack, defense = :defense, exp = :exp, exp_to_next = :exp_to_next, gold = :gold,
             current_room_id = :current_room_id
             WHERE id = :id'
        );
        $statement->execute([
            'level' => $player->level,
            'hp' => $player->hp,
            'max_hp' => $player->maxHp,
            'mp' => $player->mp,
            'max_mp' => $player->maxMp,
            'attack' => $player->attack,
            'defense' => $player->defense,
            'exp' => $player->exp,
            'exp_to_next' => $player->expToNext,
            'gold' => $player->gold,
            'current_room_id' => $player->currentRoomId,
            'id' => $player->id,
        ]);
    }
}
