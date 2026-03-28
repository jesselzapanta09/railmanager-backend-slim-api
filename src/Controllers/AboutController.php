<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutController
{
    public function index(Request $request, Response $response): Response
    {
        $data = [
            'title' => 'Slim PHP',
            'subtitle' => 'Backend Engine',
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}