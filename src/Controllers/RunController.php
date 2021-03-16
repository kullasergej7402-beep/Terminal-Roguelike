<?php

declare(strict_types=1);

namespace TerminalRoguelike\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TerminalRoguelike\Exceptions\InvalidActionException;
use TerminalRoguelike\Services\GameService;

/**
 * Handles run lifecycle HTTP endpoints (start a run, fetch current state).
 * Contains no game logic — everything is delegated to GameService.
 */
class RunController extends AbstractController
{
    public function __construct(private GameService $gameService)
    {
    }

    public function start(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $playerName = isset($body['name']) ? (string) $body['name'] : 'Аноним';

        $state = $this->gameService->startRun($playerName);

        return $this->json($response, $state, 201);
    }

    public function state(Request $request, Response $response, array $args): Response
    {
        try {
            $state = $this->gameService->getState($args['token']);
        } catch (InvalidActionException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 404);
        }

        return $this->json($response, $state);
    }
}
