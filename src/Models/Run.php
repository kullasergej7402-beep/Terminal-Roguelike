<?php

declare(strict_types=1);

namespace TerminalRoguelike\Models;

class Run
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DEAD = 'dead';
    public const STATUS_WON = 'won';

    public ?int $id;
    public string $token;
    public string $playerName;
    public string $status;
    public int $floor;
    public int $score;
    public int $enemiesKilled;
    public int $itemsFound;
    public string $startedAt;
    public ?string $endedAt;

    public function __construct(
        ?int $id,
        string $token,
        string $playerName,
        string $status,
        int $floor,
        int $score,
        int $enemiesKilled,
        int $itemsFound,
        string $startedAt,
        ?string $endedAt
    ) {
        $this->id = $id;
        $this->token = $token;
        $this->playerName = $playerName;
        $this->status = $status;
        $this->floor = $floor;
        $this->score = $score;
        $this->enemiesKilled = $enemiesKilled;
        $this->itemsFound = $itemsFound;
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['token'],
            (string) $row['player_name'],
            (string) $row['status'],
            (int) $row['floor'],
            (int) $row['score'],
            (int) $row['enemies_killed'],
            (int) $row['items_found'],
            (string) $row['started_at'],
            $row['ended_at'] !== null ? (string) $row['ended_at'] : null
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'playerName' => $this->playerName,
            'status' => $this->status,
            'floor' => $this->floor,
            'score' => $this->score,
            'enemiesKilled' => $this->enemiesKilled,
            'itemsFound' => $this->itemsFound,
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
        ];
    }
}
