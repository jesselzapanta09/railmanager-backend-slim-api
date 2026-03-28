<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Upload;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TrainController
{
    public function __construct(private PDO $pdo) {}

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Slim's getParsedBody() only auto-parses POST requests.
     * For PUT with multipart/form-data or application/x-www-form-urlencoded,
     * we need to read the raw body ourselves.
     */
    private function getBody(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (!empty($parsed) && is_array($parsed)) {
            return $parsed;
        }

        // Fallback: parse raw body as URL-encoded
        $raw = (string) $request->getBody();
        if (!empty($raw)) {
            parse_str($raw, $data);
            if (!empty($data)) {
                return $data;
            }
            // Try JSON
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }

        return [];
    }

    // ── GET /api/trains ───────────────────────────────────────────
    public function index(Request $request, Response $response): Response
    {
        try {
            $stmt   = $this->pdo->query('SELECT * FROM trains ORDER BY id DESC');
            $trains = $stmt->fetchAll();

            return $this->json($response, [
                'success' => true,
                'count'   => count($trains),
                'data'    => $trains,
            ]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── GET /api/trains/{id} ──────────────────────────────────────
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM trains WHERE id = ?');
            $stmt->execute([$args['id']]);
            $train = $stmt->fetch();

            if (!$train) {
                return $this->json($response, ['success' => false, 'message' => 'Train not found'], 404);
            }

            return $this->json($response, ['success' => true, 'data' => $train]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/trains (admin only) ─────────────────────────────
    public function store(Request $request, Response $response): Response
    {
        $body      = (array) $request->getParsedBody();
        $trainName = trim($body['train_name'] ?? '');
        $price     = $body['price'] ?? '';
        $route     = trim($body['route'] ?? '');

        if (!$trainName || $price === '' || !$route) {
            return $this->json($response, [
                'success' => false,
                'message' => 'train_name, price, and route are required',
            ], 400);
        }

        try {
            $uploadedFiles = $request->getUploadedFiles();
            $imageFile     = $uploadedFiles['image'] ?? null;
            $imageUrl      = Upload::handleTrainImage($imageFile);

            $stmt = $this->pdo->prepare(
                'INSERT INTO trains (train_name, price, route, image) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$trainName, $price, $route, $imageUrl]);
            $insertId = (int) $this->pdo->lastInsertId();

            return $this->json($response, [
                'success' => true,
                'message' => 'Train created',
                'data'    => [
                    'id'         => $insertId,
                    'train_name' => $trainName,
                    'price'      => $price,
                    'route'      => $route,
                    'image'      => $imageUrl,
                ],
            ], 201);

        } catch (\RuntimeException $e) {
            return $this->json($response, ['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── PUT /api/trains/{id} (admin only) ─────────────────────────
    public function update(Request $request, Response $response, array $args): Response
    {
        $body      = $this->getBody($request);
        $trainName = trim($body['train_name'] ?? '');
        $price     = $body['price'] ?? '';
        $route     = trim($body['route'] ?? '');

        if (!$trainName || $price === '' || !$route) {
            return $this->json($response, ['success' => false, 'message' => 'All fields are required'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM trains WHERE id = ?');
            $stmt->execute([$args['id']]);
            $existing = $stmt->fetch();

            if (!$existing) {
                return $this->json($response, ['success' => false, 'message' => 'Train not found'], 404);
            }

            $imageUrl      = $existing['image'];
            $uploadedFiles = $request->getUploadedFiles();
            $imageFile     = $uploadedFiles['image'] ?? null;

            if ($imageFile && $imageFile->getError() !== UPLOAD_ERR_NO_FILE) {
                // New image uploaded — delete old one and store new
                if ($imageUrl) {
                    Upload::deleteFile($imageUrl);
                }
                $imageUrl = Upload::handleTrainImage($imageFile);
            } elseif (($body['remove_image'] ?? '') === 'true') {
                // Explicitly removing image with no replacement
                if ($imageUrl) {
                    Upload::deleteFile($imageUrl);
                }
                $imageUrl = null;
            }

            $stmt2 = $this->pdo->prepare(
                'UPDATE trains SET train_name = ?, price = ?, route = ?, image = ? WHERE id = ?'
            );
            $stmt2->execute([$trainName, $price, $route, $imageUrl, $args['id']]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Train updated',
                'data'    => [
                    'id'         => (int) $args['id'],
                    'train_name' => $trainName,
                    'price'      => $price,
                    'route'      => $route,
                    'image'      => $imageUrl,
                ],
            ]);

        } catch (\RuntimeException $e) {
            return $this->json($response, ['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── DELETE /api/trains/{id} (admin only) ──────────────────────
    public function destroy(Request $request, Response $response, array $args): Response
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM trains WHERE id = ?');
            $stmt->execute([$args['id']]);
            $existing = $stmt->fetch();

            if (!$existing) {
                return $this->json($response, ['success' => false, 'message' => 'Train not found'], 404);
            }

            if ($existing['image']) {
                Upload::deleteFile($existing['image']);
            }

            $stmt2 = $this->pdo->prepare('DELETE FROM trains WHERE id = ?');
            $stmt2->execute([$args['id']]);

            return $this->json($response, ['success' => true, 'message' => 'Train deleted']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}