<?php

declare(strict_types=1);

namespace TerminalRoguelike\Repositories;

use PDO;
use TerminalRoguelike\Models\Enemy;

class EnemyRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(Enemy $enemy): Enemy
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO enemies (room_id, name, hp, max_hp, attack, defense, exp_reward, alive)
             VALUES (:room_id, :name, :hp, :max_hp, :attack, :defense, :exp_reward, :alive)'
        );
        $statement->execute([
            'room_id' => $enemy->roomId,
            'name' => $enemy->name,
            'hp' => $enemy->hp,
            'max_hp' => $enemy->maxHp,
            'attack' => $enemy->attack,
            'defense' => $enemy->defense,
            'exp_reward' => $enemy->expReward,
            'alive' => (int) $enemy->alive,
        ]);

        $enemy->id = (int) $this->pdo->lastInsertId();

        return $enemy;
    }

    public function findByRoomId(int $roomId): ?Enemy
    {
        $statement = $this->pdo->prepare('SELECT * FROM enemies WHERE room_id = :room_id');
        $statement->execute(['room_id' => $roomId]);
        $row = $statement->fetch();

        return $row ? Enemy::fromRow($row) : null;
    }

    public function save(Enemy $enemy): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE enemies SET hp = :hp, alive = :alive WHERE id = :id'
        );
        $statement->execute([
            'hp' => $enemy->hp,
            'alive' => (int) $enemy->alive,
            'id' => $enemy->id,
        ]);
    }
}
