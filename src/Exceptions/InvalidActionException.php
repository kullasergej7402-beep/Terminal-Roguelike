<?php

declare(strict_types=1);

namespace TerminalRoguelike\Exceptions;

/**
 * Raised when the player issues a command that is not valid in the current game state.
 */
class InvalidActionException extends GameException
{
}
