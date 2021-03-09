<?php

declare(strict_types=1);

namespace TerminalRoguelike\Models;

class Room
{
    public const TYPE_ENTRANCE = 'entrance';
    public const TYPE_COMBAT = 'combat';
    public const TYPE_TREASURE = 'treasure';
    public const TYPE_TRAP = 'trap';
    public const TYPE_REST = 'rest';
    public const TYPE_STAIRS = 'stairs';
    public const TYPE_EMPTY = 'empty';

    public ?int $id;
    public int $runId;
    public int $floor;
    public int $roomIndex;
    public string $type;
    public int $x;
    public int $y;

    /** @var array<string, int|null> */
    public array $connections;

    public bool $visited;
    public bool $cleared;

    public function __construct(
        ?int $id,
        int $runId,
        int $floor,
        int $roomIndex,
        string $type,
        int $x,
        int $y,
        array $connections,
        bool $visited,
        bool $cleared
    ) {
        $this->id = $id;
        $this->runId = $runId;
        $this->floor = $floor;
        $this->roomIndex = $roomIndex;
        $this->type = $type;
        $this->x = $x;
        $this->y = $y;
        $this->connections = $connections;
        $this->visited = $visited;
        $this->cleared = $cleared;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['run_id'],
            (int) $row['floor'],
            (int) $row['room_index'],
            (string) $row['type'],
            (int) $row['x'],
            (int) $row['y'],
            json_decode((string) $row['connections'], true) ?: [],
            (bool) $row['visited'],
            (bool) $row['cleared']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'floor' => $this->floor,
            'type' => $this->type,
            'connections' => $this->connections,
            'visited' => $this->visited,
            'cleared' => $this->cleared,
        ];
    }
}
