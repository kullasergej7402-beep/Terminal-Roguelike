<?php

declare(strict_types=1);

namespace TerminalRoguelike\Services;

use TerminalRoguelike\Models\Enemy;
use TerminalRoguelike\Models\Player;

/**
 * Handles experience gain and level-up progression for the player.
 */
class ExperienceService
{
    private const HP_GAIN_PER_LEVEL = 8;
    private const MP_GAIN_PER_LEVEL = 3;
    private const ATTACK_GAIN_PER_LEVEL = 2;
    private const DEFENSE_GAIN_PER_LEVEL = 1;
    private const EXP_TO_NEXT_GROWTH = 1.5;

    public function expRewardFor(Enemy $enemy): int
    {
        return $enemy->expReward;
    }

    /**
     * @return string[] level-up messages, empty if the player did not level up
     */
    public function addExp(Player $player, int $exp): array
    {
        $player->exp += $exp;
        $messages = [];

        while ($player->exp >= $player->expToNext) {
            $player->exp -= $player->expToNext;
            $player->level++;
            $player->maxHp += self::HP_GAIN_PER_LEVEL;
            $player->maxMp += self::MP_GAIN_PER_LEVEL;
            $player->attack += self::ATTACK_GAIN_PER_LEVEL;
            $player->defense += self::DEFENSE_GAIN_PER_LEVEL;
            $player->hp = $player->maxHp;
            $player->mp = $player->maxMp;
            $player->expToNext = (int) round($player->expToNext * self::EXP_TO_NEXT_GROWTH);

            $messages[] = sprintf(
                'Уровень повышен! Теперь ты %d уровня. HP: %d, MP: %d, атака: %d, защита: %d.',
                $player->level,
                $player->maxHp,
                $player->maxMp,
                $player->attack,
                $player->defense
            );
        }

        return $messages;
    }
}
