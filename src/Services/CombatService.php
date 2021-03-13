<?php

declare(strict_types=1);

namespace TerminalRoguelike\Services;

use TerminalRoguelike\Models\Enemy;
use TerminalRoguelike\Models\Player;

/**
 * Turn-based combat resolution between the player and a single enemy.
 */
class CombatService
{
    /**
     * @return array{damage:int, message:string}
     */
    public function playerAttack(Player $player, Enemy $enemy): array
    {
        $damage = $this->rollDamage($player->attack, $enemy->defense);
        $enemy->hp = max(0, $enemy->hp - $damage);

        $message = sprintf('Ты наносишь %d урона существу «%s».', $damage, $enemy->name);

        if ($enemy->hp <= 0) {
            $enemy->alive = false;
        }

        return ['damage' => $damage, 'message' => $message];
    }

    /**
     * @return array{damage:int, message:string}
     */
    public function enemyAttack(Enemy $enemy, Player $player): array
    {
        $damage = $this->rollDamage($enemy->attack, $player->defense);
        $player->hp = max(0, $player->hp - $damage);

        $message = sprintf('«%s» наносит тебе %d урона в ответ.', $enemy->name, $damage);

        return ['damage' => $damage, 'message' => $message];
    }

    public function isEnemyDead(Enemy $enemy): bool
    {
        return $enemy->isDead();
    }

    public function isPlayerDead(Player $player): bool
    {
        return $player->isDead();
    }

    /**
     * Base damage is attack minus defense, always at least 1, with +/-20% variance.
     */
    private function rollDamage(int $attack, int $defense): int
    {
        $base = max(1, $attack - $defense);
        $variance = (int) round($base * (random_int(-20, 20) / 100));

        return max(1, $base + $variance);
    }
}
