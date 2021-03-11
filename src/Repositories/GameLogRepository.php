<?php

declare(strict_types=1);

namespace TerminalRoguelike\Repositories;

use PDO;

class GameLogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function append(int $runId, string $message): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO game_log (run_id, message, created_at) VALUES (:run_id, :message, :created_at)'
        );
        $statement->execute([
            'run_id' => $runId,
            'message' => $message,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return string[]
     */
    public function recent(int $runId, int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            'SELECT message FROM game_log WHERE run_id = :run_id ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue('run_id', $runId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_reverse(array_column($statement->fetchAll(), 'message'));
    }
}
