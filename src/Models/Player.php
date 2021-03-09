<?php

declare(strict_types=1);

namespace TerminalRoguelike\Models;

class Player
{
    public ?int $id;
    public int $runId;
    public int $level;
    public int $hp;
    public int $maxHp;
    public int $mp;
    public int $maxMp;
    public int $attack;
    public int $defense;
    public int $exp;
    public int $expToNext;
    public int $gold;
    public ?int $currentRoomId;

    public function __construct(
        ?int $id,
        int $runId,
        int $level,
        int $hp,
        int $maxHp,
        int $mp,
        int $maxMp,
        int $attack,
        int $defense,
        int $exp,
        int $expToNext,
        int $gold,
        ?int $currentRoomId
    ) {
        $this->id = $id;
        $this->runId = $runId;
        $this->level = $level;
        $this->hp = $hp;
        $this->maxHp = $maxHp;
        $this->mp = $mp;
        $this->maxMp = $maxMp;
        $this->attack = $attack;
        $this->defense = $defense;
        $this->exp = $exp;
        $this->expToNext = $expToNext;
        $this->gold = $gold;
        $this->currentRoomId = $currentRoomId;
    }

    public static function createDefault(int $runId): self
    {
        return new self(null, $runId, 1, 30, 30, 10, 10, 6, 2, 0, 20, 0, null);
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['run_id'],
            (int) $row['level'],
            (int) $row['hp'],
            (int) $row['max_hp'],
            (int) $row['mp'],
            (int) $row['max_mp'],
            (int) $row['attack'],
            (int) $row['defense'],
            (int) $row['exp'],
            (int) $row['exp_to_next'],
            (int) $row['gold'],
            $row['current_room_id'] !== null ? (int) $row['current_room_id'] : null
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
            'runId' => $this->runId,
            'level' => $this->level,
            'hp' => $this->hp,
            'maxHp' => $this->maxHp,
            'mp' => $this->mp,
            'maxMp' => $this->maxMp,
            'attack' => $this->attack,
            'defense' => $this->defense,
            'exp' => $this->exp,
            'expToNext' => $this->expToNext,
            'gold' => $this->gold,
            'currentRoomId' => $this->currentRoomId,
        ];
    }
}
