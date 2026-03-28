<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PHP does not natively populate $_POST or parsed body for PUT requests
 * when the content type is multipart/form-data or application/x-www-form-urlencoded.
 *
 * This middleware manually parses the raw input stream for PUT (and PATCH)
 * requests and injects the result back into the PSR-7 request as parsed body,
 * making $request->getParsedBody() work exactly like it does for POST.
 */
class PutBodyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method      = strtoupper($request->getMethod());
        $contentType = $request->getHeaderLine('Content-Type');

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            $parsed = $request->getParsedBody();

            // Already parsed (Slim managed it somehow) — pass through
            if (!empty($parsed)) {
                return $handler->handle($request);
            }

            $raw = (string) $request->getBody();

            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                parse_str($raw, $data);
                $request = $request->withParsedBody($data);

            } elseif (str_contains($contentType, 'multipart/form-data')) {
                // Extract the boundary from Content-Type header
                preg_match('/boundary=(.+)$/i', $contentType, $matches);
                $boundary = $matches[1] ?? null;

                if ($boundary) {
                    [$fields, $files] = $this->parseMultipart($raw, $boundary);
                    $request = $request
                        ->withParsedBody($fields)
                        ->withUploadedFiles($this->normalizeFiles($files));
                }

            } elseif (str_contains($contentType, 'application/json')) {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    $request = $request->withParsedBody($json);
                }
            }
        }

        return $handler->handle($request);
    }

    /**
     * Parse a raw multipart body string into fields and files.
     * Returns [ ['fieldname' => 'value', ...], ['fieldname' => [...file info...], ...] ]
     */
    private function parseMultipart(string $body, string $boundary): array
    {
        $fields = [];
        $files  = [];

        // Split by boundary
        $parts = explode('--' . $boundary, $body);

        // Drop the first (empty) and last (--) parts
        array_shift($parts);
        array_pop($parts);

        foreach ($parts as $part) {
            // Separate headers from body (double CRLF)
            if (!str_contains($part, "\r\n\r\n")) {
                continue;
            }

            [$headerSection, $content] = explode("\r\n\r\n", $part, 2);

            // Strip trailing CRLF from content
            $content = rtrim($content, "\r\n");

            // Parse headers
            $headers = [];
            foreach (explode("\r\n", ltrim($headerSection)) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $val] = explode(':', $line, 2);
                    $headers[strtolower(trim($key))] = trim($val);
                }
            }

            // Extract Content-Disposition
            $disposition = $headers['content-disposition'] ?? '';
            preg_match('/name="([^"]+)"/', $disposition, $nameMatch);
            $name = $nameMatch[1] ?? null;
            if ($name === null) {
                continue;
            }

            preg_match('/filename="([^"]*)"/', $disposition, $filenameMatch);
            $filename = $filenameMatch[1] ?? null;

            if ($filename !== null) {
                // It's a file upload
                $mimeType = $headers['content-type'] ?? 'application/octet-stream';
                $tmpFile  = tempnam(sys_get_temp_dir(), 'slim_put_');
                file_put_contents($tmpFile, $content);

                $files[$name] = [
                    'name'     => $filename,
                    'type'     => $mimeType,
                    'tmp_name' => $tmpFile,
                    'error'    => $filename === '' ? UPLOAD_ERR_NO_FILE : UPLOAD_ERR_OK,
                    'size'     => strlen($content),
                ];
            } else {
                // It's a regular field
                $fields[$name] = $content;
            }
        }

        return [$fields, $files];
    }

    /**
     * Convert raw file arrays into PSR-7 UploadedFileInterface instances.
     */
    private function normalizeFiles(array $files): array
    {
        $uploadedFiles = [];

        foreach ($files as $name => $file) {
            $uploadedFiles[$name] = new \Slim\Psr7\UploadedFile(
                $file['tmp_name'],
                $file['name'],
                $file['type'],
                $file['size'],
                $file['error']
            );
        }

        return $uploadedFiles;
    }
}