<?php

declare(strict_types=1);

namespace TerminalRoguelike\Tests\Services;

use PDO;
use PHPUnit\Framework\TestCase;
use TerminalRoguelike\Models\Room;
use TerminalRoguelike\Repositories\EnemyRepository;
use TerminalRoguelike\Repositories\ItemRepository;
use TerminalRoguelike\Repositories\RoomRepository;
use TerminalRoguelike\Services\DungeonGeneratorService;

class DungeonGeneratorServiceTest extends TestCase
{
    private PDO $pdo;
    private DungeonGeneratorService $generator;
    private EnemyRepository $enemyRepository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));

        $roomRepository = new RoomRepository($this->pdo);
        $this->enemyRepository = new EnemyRepository($this->pdo);
        $itemRepository = new ItemRepository($this->pdo);

        $this->generator = new DungeonGeneratorService($roomRepository, $this->enemyRepository, $itemRepository);
    }

    private function createRun(string $token): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO runs (token, player_name, status, floor, score, enemies_killed, items_found, started_at)
             VALUES (:token, 'tester', 'active', 1, 0, 0, 0, '2026-01-01 00:00:00')"
        );
        $statement->execute(['token' => $token]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testGeneratesFloorWithinExpectedRoomCount(): void
    {
        $runId = $this->createRun('room-count');

        $rooms = $this->generator->generateFloor($runId, 1);

        $this->assertGreaterThanOrEqual(5, count($rooms));
        $this->assertLessThanOrEqual(8, count($rooms));
    }

    public function testFloorHasExactlyOneEntranceAndOneStairsRoom(): void
    {
        $runId = $this->createRun('entrance-stairs');

        $rooms = $this->generator->generateFloor($runId, 1);

        $entrances = array_filter($rooms, static fn (Room $room) => $room->type === Room::TYPE_ENTRANCE);
        $stairs = array_filter($rooms, static fn (Room $room) => $room->type === Room::TYPE_STAIRS);

        $this->assertCount(1, $entrances);
        $this->assertCount(1, $stairs);
    }

    public function testAllRoomsAreReachableFromEntrance(): void
    {
        $runId = $this->createRun('reachability');

        $rooms = $this->generator->generateFloor($runId, 1);

        $entrance = null;
        foreach ($rooms as $room) {
            if ($room->type === Room::TYPE_ENTRANCE) {
                $entrance = $room;
                break;
            }
        }
        $this->assertNotNull($entrance);

        $visited = [];
        $queue = [$entrance->id];

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            foreach ($rooms[$currentId]->connections as $neighborId) {
                if (!isset($visited[$neighborId])) {
                    $queue[] = $neighborId;
                }
            }
        }

        $this->assertCount(count($rooms), $visited, 'Every generated room must be reachable from the entrance.');
    }

    public function testCombatRoomsSpawnExactlyOneAliveEnemy(): void
    {
        $runId = $this->createRun('combat-enemy');

        $rooms = $this->generator->generateFloor($runId, 1);

        foreach ($rooms as $room) {
            if ($room->type !== Room::TYPE_COMBAT) {
                continue;
            }

            $enemy = $this->enemyRepository->findByRoomId((int) $room->id);
            $this->assertNotNull($enemy, 'Combat rooms must contain an enemy.');
            $this->assertTrue($enemy->alive);
        }
    }
}
