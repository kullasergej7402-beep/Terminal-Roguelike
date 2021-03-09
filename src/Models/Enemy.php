<?php

declare(strict_types=1);

namespace TerminalRoguelike\Models;

class Enemy
{
    public ?int $id;
    public int $roomId;
    public string $name;
    public int $hp;
    public int $maxHp;
    public int $attack;
    public int $defense;
    public int $expReward;
    public bool $alive;

    public function __construct(
        ?int $id,
        int $roomId,
        string $name,
        int $hp,
        int $maxHp,
        int $attack,
        int $defense,
        int $expReward,
        bool $alive
    ) {
        $this->id = $id;
        $this->roomId = $roomId;
        $this->name = $name;
        $this->hp = $hp;
        $this->maxHp = $maxHp;
        $this->attack = $attack;
        $this->defense = $defense;
        $this->expReward = $expReward;
        $this->alive = $alive;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['room_id'],
            (string) $row['name'],
            (int) $row['hp'],
            (int) $row['max_hp'],
            (int) $row['attack'],
            (int) $row['defense'],
            (int) $row['exp_reward'],
            (bool) $row['alive']
        );
    }

    public function isDead(): bool
    {
        return $this->hp <= 0;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hp' => $this->hp,
            'maxHp' => $this->maxHp,
            'attack' => $this->attack,
            'defense' => $this->defense,
            'alive' => $this->alive,
        ];
    }
}
