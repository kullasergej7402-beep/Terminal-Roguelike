<?php

declare(strict_types=1);

namespace TerminalRoguelike\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TerminalRoguelike\Exceptions\GameException;
use TerminalRoguelike\Exceptions\InvalidActionException;
use TerminalRoguelike\Services\GameService;

/**
 * Handles player commands. Accepts either a free-text command or a numbered
 * option — both are forwarded as-is to GameService, which resolves them to
 * the same canonical action.
 */
class ActionController extends AbstractController
{
    public function __construct(private GameService $gameService)
    {
    }

    public function handle(Request $request, Response $response, array $args): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $command = isset($body['command']) ? (string) $body['command'] : '';

        try {
            $state = $this->gameService->performAction($args['token'], $command);
        } catch (InvalidActionException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 400);
        } catch (GameException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 404);
        }

        return $this->json($response, $state);
    }
}
