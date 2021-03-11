<?php

declare(strict_types=1);

namespace TerminalRoguelike\Repositories;

use PDO;
use TerminalRoguelike\Models\Run;

class RunRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $playerName, string $token): Run
    {
        $startedAt = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO runs (token, player_name, status, floor, score, enemies_killed, items_found, started_at)
             VALUES (:token, :player_name, :status, 1, 0, 0, 0, :started_at)'
        );
        $statement->execute([
            'token' => $token,
            'player_name' => $playerName,
            'status' => Run::STATUS_ACTIVE,
            'started_at' => $startedAt,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return new Run($id, $token, $playerName, Run::STATUS_ACTIVE, 1, 0, 0, 0, $startedAt, null);
    }

    public function findByToken(string $token): ?Run
    {
        $statement = $this->pdo->prepare('SELECT * FROM runs WHERE token = :token');
        $statement->execute(['token' => $token]);
        $row = $statement->fetch();

        return $row ? Run::fromRow($row) : null;
    }

    public function save(Run $run): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE runs SET status = :status, floor = :floor, score = :score,
             enemies_killed = :enemies_killed, items_found = :items_found, ended_at = :ended_at
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $run->status,
            'floor' => $run->floor,
            'score' => $run->score,
            'enemies_killed' => $run->enemiesKilled,
            'items_found' => $run->itemsFound,
            'ended_at' => $run->endedAt,
            'id' => $run->id,
        ]);
    }

    /**
     * @return Run[]
     */
    public function findTopRuns(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM runs WHERE status != 'active' ORDER BY score DESC, started_at ASC LIMIT :limit"
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(static fn (array $row) => Run::fromRow($row), $statement->fetchAll());
    }
}
