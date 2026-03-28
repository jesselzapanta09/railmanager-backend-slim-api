<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Upload;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    public function __construct(private PDO $pdo) {}

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function deleteOldAvatar(?string $avatarPath): void
    {
        Upload::deleteFile($avatarPath);
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

    // ── GET /api/users — all users except the logged-in admin ─────
    public function index(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('user');

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, username, email, role, avatar, email_verified_at, created_at
                 FROM users WHERE id != ? ORDER BY id DESC'
            );
            $stmt->execute([$authUser['id']]);
            $users = $stmt->fetchAll();

            return $this->json($response, [
                'success' => true,
                'count'   => count($users),
                'data'    => $users,
            ]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── GET /api/users/{id} ───────────────────────────────────────
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, username, email, role, avatar, email_verified_at, created_at
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$args['id']]);
            $user = $stmt->fetch();

            if (!$user) {
                return $this->json($response, ['success' => false, 'message' => 'User not found'], 404);
            }

            return $this->json($response, ['success' => true, 'data' => $user]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/users — create user (admin, avatar optional) ────
    public function store(Request $request, Response $response): Response
    {
        $body     = (array) $request->getParsedBody();
        $username = trim($body['username'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $role     = $body['role'] ?? 'user';

        if (!$username || !$email || !$password) {
            return $this->json($response, [
                'success' => false,
                'message' => 'username, email, and password are required',
            ], 400);
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            return $this->json($response, ['success' => false, 'message' => 'role must be admin or user'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                return $this->json($response, ['success' => false, 'message' => 'Username or email already exists'], 409);
            }

            $hashed        = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            $uploadedFiles = $request->getUploadedFiles();
            $avatarFile    = $uploadedFiles['avatar'] ?? null;
            $avatarUrl     = Upload::handleAvatar($avatarFile);

            $stmt2 = $this->pdo->prepare(
                'INSERT INTO users (username, email, password, role, avatar, email_verified_at)
                 VALUES (?, ?, ?, ?, ?, null)'
            );
            $stmt2->execute([$username, $email, $hashed, $role, $avatarUrl]);
            $insertId = (int) $this->pdo->lastInsertId();

            return $this->json($response, [
                'success' => true,
                'message' => 'User created successfully',
                'data'    => [
                    'id'       => $insertId,
                    'username' => $username,
                    'email'    => $email,
                    'role'     => $role,
                    'avatar'   => $avatarUrl,
                ],
            ], 201);

        } catch (\RuntimeException $e) {
            return $this->json($response, ['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── PUT /api/users/{id} — update user (avatar optional) ───────
    public function update(Request $request, Response $response, array $args): Response
    {
        $body     = $this->getBody($request);
        $username = trim($body['username'] ?? '');
        $email    = trim($body['email'] ?? '');
        $role     = $body['role'] ?? '';
        $password = $body['password'] ?? '';
        $userId   = (int) $args['id'];
        $authUser = $request->getAttribute('user');

        if ($userId === (int) $authUser['id']) {
            return $this->json($response, [
                'success' => false,
                'message' => 'Use Edit Profile to update your own account',
            ], 400);
        }

        if (!$username || !$email || !$role) {
            return $this->json($response, [
                'success' => false,
                'message' => 'username, email, and role are required',
            ], 400);
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            return $this->json($response, ['success' => false, 'message' => 'role must be admin or user'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();

            if (!$existing) {
                return $this->json($response, ['success' => false, 'message' => 'User not found'], 404);
            }

            $stmtChk = $this->pdo->prepare(
                'SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?'
            );
            $stmtChk->execute([$username, $email, $userId]);
            if ($stmtChk->fetch()) {
                return $this->json($response, ['success' => false, 'message' => 'Username or email already taken'], 409);
            }

            // Handle avatar
            $avatarUrl     = $existing['avatar'];
            $uploadedFiles = $request->getUploadedFiles();
            $avatarFile    = $uploadedFiles['avatar'] ?? null;

            if ($avatarFile && $avatarFile->getError() !== UPLOAD_ERR_NO_FILE) {
                $this->deleteOldAvatar($avatarUrl);
                $avatarUrl = Upload::handleAvatar($avatarFile);
            }

            if (($body['remove_avatar'] ?? '') === 'true') {
                $this->deleteOldAvatar($avatarUrl);
                $avatarUrl = null;
            }

            $fields = ['username = ?', 'email = ?', 'role = ?', 'avatar = ?'];
            $values = [$username, $email, $role, $avatarUrl];

            // If email changed — reset verification status
            if ($email !== $existing['email']) {
                $fields[] = 'email_verified_at = NULL';
            }

            if ($password) {
                if (strlen($password) < 6) {
                    return $this->json($response, [
                        'success' => false,
                        'message' => 'Password must be at least 6 characters',
                    ], 400);
                }
                $fields[] = 'password = ?';
                $values[] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            }

            $values[] = $userId;
            $sql      = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $stmt2    = $this->pdo->prepare($sql);
            $stmt2->execute($values);

            return $this->json($response, [
                'success' => true,
                'message' => 'User updated successfully',
                'data'    => [
                    'id'       => $userId,
                    'username' => $username,
                    'email'    => $email,
                    'role'     => $role,
                    'avatar'   => $avatarUrl,
                ],
            ]);

        } catch (\RuntimeException $e) {
            return $this->json($response, ['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── DELETE /api/users/{id} ────────────────────────────────────
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $userId   = (int) $args['id'];
        $authUser = $request->getAttribute('user');

        if ($userId === (int) $authUser['id']) {
            return $this->json($response, ['success' => false, 'message' => 'You cannot delete your own account'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();

            if (!$existing) {
                return $this->json($response, ['success' => false, 'message' => 'User not found'], 404);
            }

            $this->deleteOldAvatar($existing['avatar']);

            $stmt2 = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt2->execute([$userId]);

            return $this->json($response, ['success' => true, 'message' => 'User deleted successfully']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}