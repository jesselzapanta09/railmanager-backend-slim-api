<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Mailer;
use App\Utils\Upload;
use Firebase\JWT\JWT;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function __construct(private PDO $pdo) {}

    // ── Helpers ───────────────────────────────────────────────────

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create / replace a user_tokens record and return the raw hex token.
     */
    private function createToken(int $userId, string $type, int $expiresInHours = 24): string
    {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresInHours * 3600);

        // Remove any existing token of the same type for this user
        $stmt = $this->pdo->prepare('DELETE FROM user_tokens WHERE user_id = ? AND type = ?');
        $stmt->execute([$userId, $type]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_tokens (user_id, token, type, expires_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $token, $type, $expiresAt]);

        return $token;
    }

    // ── POST /api/register ────────────────────────────────────────
    public function register(Request $request, Response $response): Response
    {
        $body     = (array) $request->getParsedBody();
        $username = trim($body['username'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$username || !$email || !$password) {
            return $this->json($response, ['success' => false, 'message' => 'All fields are required'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                return $this->json($response, ['success' => false, 'message' => 'Username or email already exists'], 409);
            }

            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)'
            );
            // NOTE: original code defaults to 'admin' during development — preserved exactly
            $stmt->execute([$username, $email, $hashed, 'admin']);
            $insertId = (int) $this->pdo->lastInsertId();

            $token = $this->createToken($insertId, 'email_verify', 24);
            Mailer::sendVerificationEmail($email, $username, $token);

            return $this->json($response, [
                'success' => true,
                'message' => 'Account created! Please check your email to verify your account.',
                'data'    => ['id' => $insertId, 'username' => $username, 'email' => $email, 'role' => 'user'],
            ], 201);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/login ───────────────────────────────────────────
    public function login(Request $request, Response $response): Response
    {
        $body     = (array) $request->getParsedBody();
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            return $this->json($response, ['success' => false, 'message' => 'Email and password are required'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return $this->json($response, ['success' => false, 'message' => 'Invalid email or password'], 401);
            }

            if (!password_verify($password, $user['password'])) {
                return $this->json($response, ['success' => false, 'message' => 'Invalid email or password'], 401);
            }

            if (empty($user['email_verified_at'])) {
                return $this->json($response, [
                    'success' => false,
                    'message' => 'Please verify your email before logging in.',
                    'code'    => 'EMAIL_NOT_VERIFIED',
                ], 403);
            }

            $secret  = $_ENV['JWT_SECRET'] ?? '';
            $payload = [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'iat'      => time(),
                'exp'      => time() + 86400, // 24h
            ];
            $token = JWT::encode($payload, $secret, 'HS256');

            return $this->json($response, [
                'success' => true,
                'message' => 'Login successful',
                'data'    => [
                    'token' => $token,
                    'user'  => [
                        'id'       => $user['id'],
                        'username' => $user['username'],
                        'email'    => $user['email'],
                        'role'     => $user['role'],
                        'avatar'   => $user['avatar'],
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/logout ──────────────────────────────────────────
    public function logout(Request $request, Response $response): Response
    {
        $token = $request->getAttribute('token');

        try {
            $stmt = $this->pdo->prepare('INSERT INTO token_blacklist (token) VALUES (?)');
            $stmt->execute([$token]);

            return $this->json($response, ['success' => true, 'message' => 'Logged out successfully']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── GET /api/verify-email?token=xxx ──────────────────────────
    public function verifyEmail(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $token  = $params['token'] ?? '';

        if (!$token) {
            return $this->json($response, ['success' => false, 'message' => 'Token is required'], 400);
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM user_tokens WHERE token = ? AND type = "email_verify"'
            );
            $stmt->execute([$token]);
            $row = $stmt->fetch();

            if (!$row) {
                return $this->json($response, ['success' => false, 'message' => 'Invalid or expired verification link.'], 400);
            }

            if (new \DateTime() > new \DateTime($row['expires_at'])) {
                return $this->json($response, ['success' => false, 'message' => 'Verification link has expired.'], 400);
            }

            // Check if already verified (race condition / double submit)
            $stmt2 = $this->pdo->prepare('SELECT email_verified_at FROM users WHERE id = ?');
            $stmt2->execute([$row['user_id']]);
            $userRow = $stmt2->fetch();

            if ($userRow && !empty($userRow['email_verified_at'])) {
                $stmt3 = $this->pdo->prepare('DELETE FROM user_tokens WHERE id = ?');
                $stmt3->execute([$row['id']]);
                return $this->json($response, ['success' => true, 'message' => 'Email already verified! You can log in.']);
            }

            $stmt4 = $this->pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?');
            $stmt4->execute([$row['user_id']]);

            $stmt5 = $this->pdo->prepare('DELETE FROM user_tokens WHERE id = ?');
            $stmt5->execute([$row['id']]);

            return $this->json($response, ['success' => true, 'message' => 'Email verified successfully! You can now log in.']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/resend-verification ─────────────────────────────
    public function resendVerification(Request $request, Response $response): Response
    {
        $body  = (array) $request->getParsedBody();
        $email = trim($body['email'] ?? '');

        if (!$email) {
            return $this->json($response, ['success' => false, 'message' => 'Email is required'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return $this->json($response, ['success' => false, 'message' => 'No account found with that email.'], 404);
            }

            if (!empty($user['email_verified_at'])) {
                return $this->json($response, ['success' => false, 'message' => 'Email is already verified.'], 400);
            }

            $token = $this->createToken($user['id'], 'email_verify', 24);
            Mailer::sendVerificationEmail($email, $user['username'], $token);

            return $this->json($response, ['success' => true, 'message' => 'Verification email resent! Check your inbox.']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/forgot-password ─────────────────────────────────
    public function forgotPassword(Request $request, Response $response): Response
    {
        $body  = (array) $request->getParsedBody();
        $email = trim($body['email'] ?? '');

        if (!$email) {
            return $this->json($response, ['success' => false, 'message' => 'Email is required'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Always return 200 to prevent user enumeration
            if (!$user) {
                return $this->json($response, ['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
            }

            $token = $this->createToken($user['id'], 'password_reset', 1);
            Mailer::sendPasswordResetEmail($email, $user['username'], $token);

            return $this->json($response, ['success' => true, 'message' => 'Password reset email sent! Check your inbox.']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/reset-password ──────────────────────────────────
    public function resetPassword(Request $request, Response $response): Response
    {
        $body     = (array) $request->getParsedBody();
        $token    = $body['token'] ?? '';
        $password = $body['password'] ?? '';

        if (!$token || !$password) {
            return $this->json($response, ['success' => false, 'message' => 'Token and new password are required'], 400);
        }

        if (strlen($password) < 6) {
            return $this->json($response, ['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM user_tokens WHERE token = ? AND type = "password_reset"'
            );
            $stmt->execute([$token]);
            $row = $stmt->fetch();

            if (!$row) {
                return $this->json($response, ['success' => false, 'message' => 'Invalid or expired reset link.'], 400);
            }

            if (new \DateTime() > new \DateTime($row['expires_at'])) {
                return $this->json($response, ['success' => false, 'message' => 'Reset link has expired. Please request a new one.'], 400);
            }

            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

            $stmt2 = $this->pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt2->execute([$hashed, $row['user_id']]);

            $stmt3 = $this->pdo->prepare('DELETE FROM user_tokens WHERE id = ?');
            $stmt3->execute([$row['id']]);

            return $this->json($response, ['success' => true, 'message' => 'Password reset successfully! You can now log in.']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/change-password (authenticated) ─────────────────
    public function changePassword(Request $request, Response $response): Response
    {
        $body            = (array) $request->getParsedBody();
        $currentPassword = $body['currentPassword'] ?? '';
        $newPassword     = $body['newPassword'] ?? '';
        $authUser        = $request->getAttribute('user');

        if (!$currentPassword || !$newPassword) {
            return $this->json($response, ['success' => false, 'message' => 'Both passwords are required'], 400);
        }

        if (strlen($newPassword) < 6) {
            return $this->json($response, ['success' => false, 'message' => 'New password must be at least 6 characters'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$authUser['id']]);
            $user = $stmt->fetch();

            if (!password_verify($currentPassword, $user['password'])) {
                return $this->json($response, ['success' => false, 'message' => 'Current password is incorrect'], 401);
            }

            $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
            $stmt2  = $this->pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt2->execute([$hashed, $authUser['id']]);

            return $this->json($response, ['success' => true, 'message' => 'Password changed successfully!']);

        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/update-profile (authenticated) ──────────────────
    public function updateProfile(Request $request, Response $response): Response
    {
        $body         = (array) $request->getParsedBody();
        $username     = trim($body['username'] ?? '');
        $email        = trim($body['email'] ?? '');
        $removeAvatar = ($body['remove_avatar'] ?? '') === 'true';
        $authUser     = $request->getAttribute('user');

        if (!$username) {
            return $this->json($response, ['success' => false, 'message' => 'Username is required'], 400);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$authUser['id']]);
            $current = $stmt->fetch();

            // Check username uniqueness
            if ($username !== $current['username']) {
                $stmtChk = $this->pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
                $stmtChk->execute([$username, $authUser['id']]);
                if ($stmtChk->fetch()) {
                    return $this->json($response, ['success' => false, 'message' => 'Username is already taken'], 409);
                }
            }

            // Check email uniqueness
            $newEmail = $email ?: $current['email'];
            if ($newEmail !== $current['email']) {
                $stmtChk2 = $this->pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
                $stmtChk2->execute([$newEmail, $authUser['id']]);
                if ($stmtChk2->fetch()) {
                    return $this->json($response, ['success' => false, 'message' => 'Email is already in use by another account'], 409);
                }
            }

            // Handle avatar upload
            $uploadedFiles = $request->getUploadedFiles();
            $avatarFile    = $uploadedFiles['avatar'] ?? null;
            $avatarUrl     = $current['avatar'];

            if ($avatarFile && $avatarFile->getError() !== UPLOAD_ERR_NO_FILE) {
                if ($avatarUrl) {
                    Upload::deleteFile($avatarUrl);
                }
                $avatarUrl = Upload::handleAvatar($avatarFile);
            }

            if ($removeAvatar) {
                if ($avatarUrl) {
                    Upload::deleteFile($avatarUrl);
                }
                $avatarUrl = null;
            }

            $emailChanged = $newEmail !== $current['email'];
            $sql          = 'UPDATE users SET username = ?, email = ?, avatar = ?'
                . ($emailChanged ? ', email_verified_at = NULL' : '')
                . ' WHERE id = ?';

            $stmt2 = $this->pdo->prepare($sql);
            $stmt2->execute([$username, $newEmail, $avatarUrl, $authUser['id']]);

            $stmt3 = $this->pdo->prepare(
                'SELECT id, username, email, role, avatar FROM users WHERE id = ?'
            );
            $stmt3->execute([$authUser['id']]);
            $updated = $stmt3->fetch();

            return $this->json($response, [
                'success'      => true,
                'message'      => $emailChanged
                    ? 'Email updated! Please verify your new email address before logging in again.'
                    : 'Profile updated successfully!',
                'data'         => $updated,
                'emailChanged' => $emailChanged,
            ]);

        } catch (\RuntimeException $e) {
            // Upload validation errors
            return $this->json($response, ['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json($response, ['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}
