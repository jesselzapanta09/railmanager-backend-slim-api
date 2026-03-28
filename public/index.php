<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// ── Load .env ────────────────────────────────────────────────────
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// ── Build DI Container ───────────────────────────────────────────
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../src/Config/container.php');
$container = $containerBuilder->build();

// ── Create App ───────────────────────────────────────────────────
AppFactory::setContainer($container);
$app = AppFactory::create();

// ── Global OPTIONS catch-all (preflight) ────────────────────────
$app->options('/{routes:.+}', function ($request, $response) {
    return $response->withStatus(200);
});

// ── CORS Middleware (outermost) ──────────────────────────────────
$app->add(new App\Middleware\CorsMiddleware());

// ── PUT/PATCH body parser ────────────────────────────────────────
// Must run BEFORE the built-in body parsing middleware.
// Handles multipart/form-data and urlencoded bodies on PUT/PATCH,
// which PHP does not parse natively.
$app->add(new App\Middleware\PutBodyMiddleware());

// ── Body Parsing Middleware (handles POST automatically) ─────────
$app->addBodyParsingMiddleware();

// ── Routing Middleware ───────────────────────────────────────────
$app->addRoutingMiddleware();

// ── Error Middleware ─────────────────────────────────────────────
$errorMiddleware = $app->addErrorMiddleware(
    ($_ENV['APP_ENV'] ?? 'development') === 'development',
    true,
    true
);
$errorMiddleware->setDefaultErrorHandler(
    new App\Middleware\ErrorHandler(
        $app->getCallableResolver(),
        $app->getResponseFactory()
    )
);

// ── Register Routes ──────────────────────────────────────────────
(require __DIR__ . '/../src/Config/routes.php')($app);

$app->run();