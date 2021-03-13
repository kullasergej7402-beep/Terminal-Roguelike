<?php

declare(strict_types=1);

namespace TerminalRoguelike\Services;

use TerminalRoguelike\Models\Enemy;
use TerminalRoguelike\Models\Item;
use TerminalRoguelike\Models\Room;
use TerminalRoguelike\Repositories\EnemyRepository;
use TerminalRoguelike\Repositories\ItemRepository;
use TerminalRoguelike\Repositories\RoomRepository;

/**
 * Builds a floor as a connected graph of rooms using a random walk on a grid,
 * then assigns room types, enemies and treasure.
 */
class DungeonGeneratorService
{
    private const DIRECTIONS = [
        'north' => [0, -1],
        'south' => [0, 1],
        'east' => [1, 0],
        'west' => [-1, 0],
    ];

    private const OPPOSITE = [
        'north' => 'south',
        'south' => 'north',
        'east' => 'west',
        'west' => 'east',
    ];

    private const ENEMY_TEMPLATES = [
        ['name' => 'скрипт-кидди', 'hp' => 16, 'attack' => 5, 'defense' => 1, 'exp' => 10],
        ['name' => 'бот-снифер',   'hp' => 22, 'attack' => 6, 'defense' => 2, 'exp' => 14],
        ['name' => 'троян',        'hp' => 28, 'attack' => 8, 'defense' => 3, 'exp' => 20],
        ['name' => 'файрвол-страж', 'hp' => 40, 'attack' => 9, 'defense' => 5, 'exp' => 30],
        ['name' => 'руткит',       'hp' => 34, 'attack' => 11, 'defense' => 2, 'exp' => 26],
    ];

    private const ITEM_TEMPLATES = [
        ['name' => 'заточенный скрипт-нож', 'type' => Item::TYPE_WEAPON, 'effect' => 3],
        ['name' => 'exploit-клинок',        'type' => Item::TYPE_WEAPON, 'effect' => 5],
        ['name' => 'бронежилет прокси',     'type' => Item::TYPE_ARMOR, 'effect' => 2],
        ['name' => 'firewall-щит',          'type' => Item::TYPE_ARMOR, 'effect' => 4],
        ['name' => 'зелье восстановления',  'type' => Item::TYPE_POTION, 'effect' => 15],
    ];

    public function __construct(
        private RoomRepository $roomRepository,
        private EnemyRepository $enemyRepository,
        private ItemRepository $itemRepository
    ) {
    }

    /**
     * @return Room[] rooms indexed by their DB id
     */
    public function generateFloor(int $runId, int $floor): array
    {
        $layout = $this->buildLayout();
        $rooms = $this->persistLayout($runId, $floor, $layout);
        $this->populateRooms($rooms, $layout['types']);

        return $rooms;
    }

    /**
     * Random walk on an integer grid, producing 5-8 connected cells plus a
     * handful of extra edges so the map is a graph rather than a pure tree.
     *
     * @return array{cells: array<int, array{x:int,y:int,connections:array<string,int>}>, types: array<int,string>}
     */
    private function buildLayout(): array
    {
        $roomCount = random_int(5, 8);

        $cells = [0 => ['x' => 0, 'y' => 0, 'connections' => []]];
        $occupied = ['0,0' => 0];
        $frontier = [0];
        $nextIndex = 1;

        while ($nextIndex < $roomCount && !empty($frontier)) {
            $fromIndex = $frontier[array_rand($frontier)];
            $from = $cells[$fromIndex];
            $directions = array_keys(self::DIRECTIONS);
            shuffle($directions);
            $placed = false;

            foreach ($directions as $direction)
            {
                [$dx, $dy] = self::DIRECTIONS[$direction];
                $nx = $from['x'] + $dx;
                $ny = $from['y'] + $dy;
                $key = "{$nx},{$ny}";

                if (isset($occupied[$key])) {
                    continue;
                }

                $cells[$nextIndex] = ['x' => $nx, 'y' => $ny, 'connections' => []];
                $occupied[$key] = $nextIndex;

                $cells[$fromIndex]['connections'][$direction] = $nextIndex;
                $cells[$nextIndex]['connections'][self::OPPOSITE[$direction]] = $fromIndex;

                $frontier[] = $nextIndex;
                $nextIndex++;
                $placed = true;
                break;
            }

            if (!$placed) {
                $frontier = array_values(array_filter($frontier, static fn (int $i): bool => $i !== $fromIndex));
            }
        }

        // Sprinkle a few extra edges between already-adjacent rooms so the
        // dungeon is a graph with occasional loops, not just a tree.
        foreach ($cells as $index => $cell) {
            foreach (self::DIRECTIONS as $direction => [$dx, $dy]) {
                if (isset($cell['connections'][$direction])) {
                    continue;
                }

                $key = ($cell['x'] + $dx) . ',' . ($cell['y'] + $dy);
                if (isset($occupied[$key]) && random_int(1, 100) <= 20) {
                    $neighborIndex = $occupied[$key];
                    $cells[$index]['connections'][$direction] = $neighborIndex;
                    $cells[$neighborIndex]['connections'][self::OPPOSITE[$direction]] = $index;
                }
            }
        }

        $types = $this->assignTypes($cells);

        return ['cells' => $cells, 'types' => $types];
    }

    /**
     * @param array<int, array{x:int,y:int,connections:array<string,int>}> $cells
     * @return array<int, string> room index => room type
     */
    private function assignTypes(array $cells): array
    {
        $indices = array_keys($cells);
        $entranceIndex = 0;
        $stairsIndex = end($indices);

        $types = [
            $entranceIndex => Room::TYPE_ENTRANCE,
            $stairsIndex => Room::TYPE_STAIRS,
        ];

        $remaining = array_values(array_diff($indices, [$entranceIndex, $stairsIndex]));
        shuffle($remaining);

        $weights = [
            Room::TYPE_COMBAT => 45,
            Room::TYPE_TREASURE => 20,
            Room::TYPE_TRAP => 20,
            Room::TYPE_REST => 15,
        ];

        foreach ($remaining as $index) {
            $types[$index] = $this->weightedRandomType($weights);
        }

        if (count($remaining) >= 3 && !in_array(Room::TYPE_REST, $types, true)) {
            $types[$remaining[0]] = Room::TYPE_REST;
        }

        return $types;
    }

    /**
     * @param array<string,int> $weights
     */
    private function weightedRandomType(array $weights): string
    {
        $total = array_sum($weights);
        $roll = random_int(1, $total);
        $cumulative = 0;

        foreach ($weights as $type => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $type;
            }
        }

        return Room::TYPE_COMBAT;
    }

    /**
     * @param array{cells: array<int, array{x:int,y:int,connections:array<string,int>}>, types: array<int,string>} $layout
     * @return Room[] indexed by DB id
     */
    private function persistLayout(int $runId, int $floor, array $layout): array
    {
        $indexToRoom = [];

        foreach ($layout['cells'] as $index => $cell) {
            $room = new Room(
                null,
                $runId,
                $floor,
                $index,
                $layout['types'][$index],
                $cell['x'],
                $cell['y'],
                [],
                false,
                false
            );
            $indexToRoom[$index] = $this->roomRepository->create($room);
        }

        $rooms = [];
        foreach ($layout['cells'] as $index => $cell) {
            $room = $indexToRoom[$index];
            $connections = [];
            foreach ($cell['connections'] as $direction => $neighborIndex) {
                $connections[$direction] = $indexToRoom[$neighborIndex]->id;
            }
            $room->connections = $connections;
            $this->roomRepository->updateConnections($room);
            $rooms[$room->id] = $room;
        }

        return $rooms;
    }

    /**
     * @param Room[] $rooms
     * @param array<int,string> $typesByIndex
     */
    private function populateRooms(array $rooms, array $typesByIndex): void
    {
        foreach ($rooms as $room) {
            if ($room->type === Room::TYPE_COMBAT) {
                $template = self::ENEMY_TEMPLATES[array_rand(self::ENEMY_TEMPLATES)];
                $this->enemyRepository->create(new Enemy(
                    null,
                    $room->id,
                    $template['name'],
                    $template['hp'],
                    $template['hp'],
                    $template['attack'],
                    $template['defense'],
                    $template['exp'],
                    true
                ));
            }

            if ($room->type === Room::TYPE_TREASURE) {
                $template = self::ITEM_TEMPLATES[array_rand(self::ITEM_TEMPLATES)];
                $this->itemRepository->create(new Item(
                    null,
                    $room->id,
                    null,
                    $template['name'],
                    $template['type'],
                    $template['effect'],
                    false,
                    false
                ));
            }
        }
    }
}
