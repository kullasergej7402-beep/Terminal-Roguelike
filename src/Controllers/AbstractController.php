<?php

declare(strict_types=1);

namespace TerminalRoguelike\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

abstract class AbstractController
{
    protected function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
