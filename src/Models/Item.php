<?php

declare(strict_types=1);

namespace TerminalRoguelike\Models;

class Item
{
    public const TYPE_WEAPON = 'weapon';
    public const TYPE_ARMOR = 'armor';
    public const TYPE_POTION = 'potion';

    public ?int $id;
    public ?int $roomId;
    public ?int $playerId;
    public string $name;
    public string $type;
    public int $effectValue;
    public bool $equipped;
    public bool $taken;

    public function __construct(
        ?int $id,
        ?int $roomId,
        ?int $playerId,
        string $name,
        string $type,
        int $effectValue,
        bool $equipped,
        bool $taken
    ) {
        $this->id = $id;
        $this->roomId = $roomId;
        $this->playerId = $playerId;
        $this->name = $name;
        $this->type = $type;
        $this->effectValue = $effectValue;
        $this->equipped = $equipped;
        $this->taken = $taken;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['room_id'] !== null ? (int) $row['room_id'] : null,
            $row['player_id'] !== null ? (int) $row['player_id'] : null,
            (string) $row['name'],
            (string) $row['type'],
            (int) $row['effect_value'],
            (bool) $row['equipped'],
            (bool) $row['taken']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'effectValue' => $this->effectValue,
            'equipped' => $this->equipped,
        ];
    }
}
