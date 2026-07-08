<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use TerminalRoguelike\Controllers\ActionController;
use TerminalRoguelike\Controllers\LeaderboardController;
use TerminalRoguelike\Controllers\RunController;
use TerminalRoguelike\Database\Database;
use TerminalRoguelike\Repositories\EnemyRepository;
use TerminalRoguelike\Repositories\GameLogRepository;
use TerminalRoguelike\Repositories\ItemRepository;
use TerminalRoguelike\Repositories\PlayerRepository;
use TerminalRoguelike\Repositories\RoomRepository;
use TerminalRoguelike\Repositories\RunRepository;
use TerminalRoguelike\Services\CombatService;
use TerminalRoguelike\Services\DungeonGeneratorService;
use TerminalRoguelike\Services\ExperienceService;
use TerminalRoguelike\Services\GameService;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $file = $root . '/public' . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

$dotenv = Dotenv::createImmutable($root);
$dotenv->safeLoad();

$dbPath = $root . '/' . ($_ENV['DB_PATH'] ?? 'database/game.sqlite');
$pdo = Database::connection($dbPath);

$runRepository = new RunRepository($pdo);
$playerRepository = new PlayerRepository($pdo);
$roomRepository = new RoomRepository($pdo);
$enemyRepository = new EnemyRepository($pdo);
$itemRepository = new ItemRepository($pdo);
$gameLogRepository = new GameLogRepository($pdo);

$dungeonGenerator = new DungeonGeneratorService($roomRepository, $enemyRepository, $itemRepository);
$combatService = new CombatService();
$experienceService = new ExperienceService();

$gameService = new GameService(
    $runRepository,
    $playerRepository,
    $roomRepository,
    $enemyRepository,
    $itemRepository,
    $gameLogRepository,
    $dungeonGenerator,
    $combatService,
    $experienceService
);

$runController = new RunController($gameService);
$actionController = new ActionController($gameService);
$leaderboardController = new LeaderboardController($gameService);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$isDevelopment = ($_ENV['APP_ENV'] ?? 'production') === 'development';
$app->addErrorMiddleware($isDevelopment, true, true);

$app->get('/', function ($request, $response) use ($root) {
    $html = file_get_contents($root . '/public/index.html');
    $response->getBody()->write((string) $html);

    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->post('/api/run/start', [$runController, 'start']);
$app->get('/api/run/{token}', [$runController, 'state']);
$app->post('/api/run/{token}/action', [$actionController, 'handle']);
$app->get('/api/leaderboard', [$leaderboardController, 'index']);

$app->run();
