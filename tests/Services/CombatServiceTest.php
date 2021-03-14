<?php

declare(strict_types=1);

namespace TerminalRoguelike\Tests\Services;

use PHPUnit\Framework\TestCase;
use TerminalRoguelike\Models\Enemy;
use TerminalRoguelike\Models\Player;
use TerminalRoguelike\Services\CombatService;

class CombatServiceTest extends TestCase
{
    public function testPlayerAttackReducesEnemyHp(): void
    {
        $service = new CombatService();
        $player = Player::createDefault(1);
        $player->attack = 10;
        $enemy = new Enemy(1, 1, 'скрипт-кидди', 20, 20, 5, 2, 10, true);

        $result = $service->playerAttack($player, $enemy);

        $this->assertLessThan(20, $enemy->hp);
        $this->assertGreaterThan(0, $result['damage']);
    }

    public function testEnemyDiesWhenHpReachesZero(): void
    {
        $service = new CombatService();
        $player = Player::createDefault(1);
        $player->attack = 999;
        $enemy = new Enemy(1, 1, 'слабый бот', 5, 5, 5, 0, 10, true);

        $service->playerAttack($player, $enemy);

        $this->assertTrue($service->isEnemyDead($enemy));
        $this->assertFalse($enemy->alive);
    }

    public function testEnemyAttackReducesPlayerHp(): void
    {
        $service = new CombatService();
        $player = Player::createDefault(1);
        $enemy = new Enemy(1, 1, 'троян', 20, 20, 10, 0, 10, true);

        $service->enemyAttack($enemy, $player);

        $this->assertLessThan($player->maxHp, $player->hp);
    }

    public function testDamageIsNeverLessThanOne(): void
    {
        $service = new CombatService();
        $player = Player::createDefault(1);
        $player->attack = 1;
        $enemy = new Enemy(1, 1, 'танк', 100, 100, 1, 999, 10, true);

        $result = $service->playerAttack($player, $enemy);

        $this->assertSame(1, $result['damage']);
    }

    public function testIsPlayerDeadReflectsHp(): void
    {
        $service = new CombatService();
        $player = Player::createDefault(1);

        $this->assertFalse($service->isPlayerDead($player));

        $player->hp = 0;

        $this->assertTrue($service->isPlayerDead($player));
    }
}
