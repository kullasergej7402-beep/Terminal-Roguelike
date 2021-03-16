<?php

declare(strict_types=1);

namespace TerminalRoguelike\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TerminalRoguelike\Services\GameService;

class LeaderboardController extends AbstractController
{
    public function __construct(private GameService $gameService)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $runs = $this->gameService->leaderboard(10);

        return $this->json($response, ['leaderboard' => $runs]);
    }
}
