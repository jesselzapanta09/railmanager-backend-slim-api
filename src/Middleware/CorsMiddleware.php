<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $allowedOriginsRaw = $_ENV['ALLOWED_ORIGINS'] ?? '';
        $allowedOrigins    = array_map('trim', explode(',', $allowedOriginsRaw));
        $origin            = $request->getHeaderLine('Origin');

        // Handle pre-flight OPTIONS — must respond BEFORE routing runs,
        // otherwise Slim throws "Method not allowed" for OPTIONS.
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            return $this->addCorsHeaders($response, $origin, $allowedOrigins)
                ->withStatus(200);
        }

        $response = $handler->handle($request);

        return $this->addCorsHeaders($response, $origin, $allowedOrigins);
    }

    private function addCorsHeaders(
        ResponseInterface $response,
        string $origin,
        array $allowedOrigins
    ): ResponseInterface {
        // No origin header = Postman / mobile / server-to-server — allow it
        if ($origin === '') {
            return $response
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if (in_array($origin, $allowedOrigins, true)) {
            return $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Origin not in allowlist — no CORS headers, browser will block it
        return $response;
    }
}