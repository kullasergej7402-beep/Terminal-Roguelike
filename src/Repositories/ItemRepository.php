<?php

declare(strict_types=1);

namespace TerminalRoguelike\Repositories;

use PDO;
use TerminalRoguelike\Models\Item;

class ItemRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(Item $item): Item
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO items (room_id, player_id, name, type, effect_value, equipped, taken)
             VALUES (:room_id, :player_id, :name, :type, :effect_value, :equipped, :taken)'
        );
        $statement->execute([
            'room_id' => $item->roomId,
            'player_id' => $item->playerId,
            'name' => $item->name,
            'type' => $item->type,
            'effect_value' => $item->effectValue,
            'equipped' => (int) $item->equipped,
            'taken' => (int) $item->taken,
        ]);

        $item->id = (int) $this->pdo->lastInsertId();

        return $item;
    }

    public function findUntakenByRoomId(int $roomId): ?Item
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM items WHERE room_id = :room_id AND taken = 0 LIMIT 1'
        );
        $statement->execute(['room_id' => $roomId]);
        $row = $statement->fetch();

        return $row ? Item::fromRow($row) : null;
    }

    /**
     * @return Item[]
     */
    public function findInventory(int $playerId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM items WHERE player_id = :player_id AND taken = 1 ORDER BY id ASC'
        );
        $statement->execute(['player_id' => $playerId]);

        return array_map(static fn (array $row) => Item::fromRow($row), $statement->fetchAll());
    }

    public function findInventoryItemByType(int $playerId, string $type): ?Item
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM items WHERE player_id = :player_id AND taken = 1 AND type = :type LIMIT 1'
        );
        $statement->execute(['player_id' => $playerId, 'type' => $type]);
        $row = $statement->fetch();

        return $row ? Item::fromRow($row) : null;
    }

    public function assignToPlayer(Item $item, int $playerId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE items SET player_id = :player_id, taken = 1, room_id = NULL WHERE id = :id'
        );
        $statement->execute(['player_id' => $playerId, 'id' => $item->id]);
    }

    public function delete(Item $item): void
    {
        $statement = $this->pdo->prepare('DELETE FROM items WHERE id = :id');
        $statement->execute(['id' => $item->id]);
    }
}
