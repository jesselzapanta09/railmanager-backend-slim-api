<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Database;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('Access token required');
        }

        $token = substr($authHeader, 7);

        try {
            $pdo = Database::getConnection();

            // ── Check blacklist ──────────────────────────────────
            $stmt = $pdo->prepare('SELECT id FROM token_blacklist WHERE token = ?');
            $stmt->execute([$token]);
            if ($stmt->fetch()) {
                return $this->unauthorized('Token has been invalidated. Please login again.');
            }

            // ── Verify JWT ───────────────────────────────────────
            $secret  = $_ENV['JWT_SECRET'] ?? '';
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // Attach decoded user + raw token to request attributes
            $request = $request
                ->withAttribute('user', (array) $decoded)
                ->withAttribute('token', $token);

            return $handler->handle($request);

        } catch (ExpiredException | SignatureInvalidException | \UnexpectedValueException $e) {
            return $this->forbidden('Invalid or expired token');
        } catch (\Exception $e) {
            return $this->forbidden('Invalid or expired token');
        }
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    private function forbidden(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }
}
