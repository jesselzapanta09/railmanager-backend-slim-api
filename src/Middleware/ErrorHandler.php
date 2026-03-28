<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Throwable;

class ErrorHandler extends SlimErrorHandler
{
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory
    ) {
        parent::__construct($callableResolver, $responseFactory);
    }

    protected function respond(): ResponseInterface
    {
        $exception  = $this->exception;
        $statusCode = 500;

        if ($exception instanceof \Slim\Exception\HttpNotFoundException) {
            $statusCode = 404;
        } elseif ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException) {
            $statusCode = 405;
        }

        $payload = [
            'success' => false,
            'message' => $statusCode === 500 ? 'Internal Server Error' : $exception->getMessage(),
        ];

        if (($_ENV['APP_ENV'] ?? 'development') === 'development' && $statusCode === 500) {
            $payload['error'] = $exception->getMessage();
            $payload['trace'] = $exception->getTraceAsString();
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
