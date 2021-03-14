<?php

declare(strict_types=1);

namespace TerminalRoguelike\Tests\Services;

use PHPUnit\Framework\TestCase;
use TerminalRoguelike\Models\Enemy;
use TerminalRoguelike\Models\Player;
use TerminalRoguelike\Services\ExperienceService;

class ExperienceServiceTest extends TestCase
{
    public function testAddExpWithoutLevelUp(): void
    {
        $service = new ExperienceService();
        $player = Player::createDefault(1);

        $messages = $service->addExp($player, 5);

        $this->assertSame(5, $player->exp);
        $this->assertSame(1, $player->level);
        $this->assertEmpty($messages);
    }

    public function testAddExpTriggersLevelUp(): void
    {
        $service = new ExperienceService();
        $player = Player::createDefault(1);
        $initialMaxHp = $player->maxHp;

        $messages = $service->addExp($player, 25);

        $this->assertSame(2, $player->level);
        $this->assertGreaterThan($initialMaxHp, $player->maxHp);
        $this->assertSame($player->maxHp, $player->hp, 'Player should be fully healed on level up.');
        $this->assertCount(1, $messages);
    }

    public function testAddExpCanTriggerMultipleLevelUpsAtOnce(): void
    {
        $service = new ExperienceService();
        $player = Player::createDefault(1);

        $messages = $service->addExp($player, 500);

        $this->assertGreaterThan(2, $player->level);
        $this->assertCount($player->level - 1, $messages);
    }

    public function testExpRewardForReturnsEnemyValue(): void
    {
        $service = new ExperienceService();
        $enemy = new Enemy(1, 1, 'руткит', 10, 10, 1, 1, 42, true);

        $this->assertSame(42, $service->expRewardFor($enemy));
    }
}
