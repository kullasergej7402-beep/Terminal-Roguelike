<?php

declare(strict_types=1);

namespace TerminalRoguelike\Repositories;

use PDO;
use TerminalRoguelike\Models\Room;

class RoomRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(Room $room): Room
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO rooms (run_id, floor, room_index, type, x, y, connections, visited, cleared)
             VALUES (:run_id, :floor, :room_index, :type, :x, :y, :connections, :visited, :cleared)'
        );
        $statement->execute([
            'run_id' => $room->runId,
            'floor' => $room->floor,
            'room_index' => $room->roomIndex,
            'type' => $room->type,
            'x' => $room->x,
            'y' => $room->y,
            'connections' => json_encode($room->connections),
            'visited' => (int) $room->visited,
            'cleared' => (int) $room->cleared,
        ]);

        $room->id = (int) $this->pdo->lastInsertId();

        return $room;
    }

    public function findById(int $id): ?Room
    {
        $statement = $this->pdo->prepare('SELECT * FROM rooms WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? Room::fromRow($row) : null;
    }

    /**
     * @return Room[]
     */
    public function findByRunAndFloor(int $runId, int $floor): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM rooms WHERE run_id = :run_id AND floor = :floor ORDER BY room_index ASC'
        );
        $statement->execute(['run_id' => $runId, 'floor' => $floor]);

        return array_map(static fn (array $row) => Room::fromRow($row), $statement->fetchAll());
    }

    public function updateConnections(Room $room): void
    {
        $statement = $this->pdo->prepare('UPDATE rooms SET connections = :connections WHERE id = :id');
        $statement->execute([
            'connections' => json_encode($room->connections),
            'id' => $room->id,
        ]);
    }

    public function save(Room $room): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE rooms SET visited = :visited, cleared = :cleared WHERE id = :id'
        );
        $statement->execute([
            'visited' => (int) $room->visited,
            'cleared' => (int) $room->cleared,
            'id' => $room->id,
        ]);
    }
}
