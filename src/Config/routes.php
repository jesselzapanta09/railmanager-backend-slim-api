<?php

declare(strict_types=1);

use Slim\App;
use App\Controllers\AboutController;
use App\Controllers\AuthController;
use App\Controllers\TrainController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;

return function (App $app): void {

    // ── Root ─────────────────────────────────────────────────────
    $app->get('/', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'RailManager API v2.0',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // ── Static uploads — serve image files directly ───────────────
    // Must be declared BEFORE the 404 catch-all.
    $app->get('/uploads/{type}/{filename}', function ($request, $response, array $args) {
        $type     = $args['type'];
        $filename = $args['filename'];

        // Only allow avatars and trains subdirectories
        if (!in_array($type, ['avatars', 'trains'], true)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $filePath = __DIR__ . '/../../uploads/' . $type . '/' . $filename;

        if (!file_exists($filePath) || !is_file($filePath)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'File not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeMap  = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        ];
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';

        $response->getBody()->write(file_get_contents($filePath));
        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'public, max-age=86400');
    });

    // ── Auth Routes (prefix: /api) ────────────────────────────────
    
    $app->get('/api/about',                  [AboutController::class, 'index']);

    $app->post('/api/register',              [AuthController::class, 'register']);
    $app->post('/api/login',                 [AuthController::class, 'login']);
    $app->post('/api/logout',                [AuthController::class, 'logout'])
        ->add(new AuthMiddleware());
    $app->get('/api/verify-email',           [AuthController::class, 'verifyEmail']);
    $app->post('/api/resend-verification',   [AuthController::class, 'resendVerification']);
    $app->post('/api/forgot-password',       [AuthController::class, 'forgotPassword']);
    $app->post('/api/reset-password',        [AuthController::class, 'resetPassword']);
    $app->post('/api/change-password',       [AuthController::class, 'changePassword'])
        ->add(new AuthMiddleware());
    $app->post('/api/update-profile',        [AuthController::class, 'updateProfile'])
        ->add(new AuthMiddleware());

    // ── Train Routes (prefix: /api/trains) ───────────────────────
    $app->get('/api/trains',        [TrainController::class, 'index'])
        ->add(new AuthMiddleware());
    $app->get('/api/trains/{id}',   [TrainController::class, 'show'])
        ->add(new AuthMiddleware());
    $app->post('/api/trains',       [TrainController::class, 'store'])
        ->add(new AdminMiddleware())
        ->add(new AuthMiddleware());
    $app->put('/api/trains/{id}',   [TrainController::class, 'update'])
        ->add(new AdminMiddleware())
        ->add(new AuthMiddleware());
    $app->delete('/api/trains/{id}', [TrainController::class, 'destroy'])
        ->add(new AdminMiddleware())
        ->add(new AuthMiddleware());

    // ── User Routes (prefix: /api/users) ─────────────────────────
    $app->get('/api/users',         [UserController::class, 'index'])
        ->add(new AdminMiddleware())
        ->add(new AuthMiddleware());
    $app->get('/api/users/{id}',    [UserController::class, 'show'])
        ->add(new AdminMiddleware())
        ->add(new AuthMiddleware());
    $app->post('/api/users',        [UserController::class, 'store'])
        ->add(new AdminMiddleware())
        ->add(new AuthMiddleware());
    $app->put('/api/users/{id}',    [UserController::class, 'update'])
        ->add(new AdminMiddleware())
        ->add(new AuthMiddleware());
    $app->delete('/api/users/{id}', [UserController::class, 'destroy'])
        ->add(new AdminMiddleware())
        ->add(new AuthMiddleware());

    // ── 404 fallback ─────────────────────────────────────────────
    $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Route not found',
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    });
};