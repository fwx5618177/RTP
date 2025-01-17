<?php

namespace App\Http;

class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $bodyParams;
    private array $headers;
    private array $cookies;
    private array $files;
    private $container;

    public function __construct(
        string $method,
        string $path,
        array $server = [],
        array $queryParams = [],
        array $bodyParams = [],
        array $cookies = [],
        array $files = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
        $this->headers = $this->extractHeaders($server);
        $this->cookies = $cookies;
        $this->files = $files;
    }

    public static function createFromGlobals(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES
        );
    }

    public static function createFromStream($stream): self
    {
        $rawRequest = stream_get_contents($stream);
        $lines = explode("\r\n", $rawRequest);

        // Parse request line
        $requestLine = array_shift($lines);
        [$method, $path] = explode(' ', $requestLine);

        // Parse headers
        $headers = [];
        while ($line = array_shift($lines)) {
            if (empty($line)) break;
            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        // Parse body
        $body = implode("\r\n", $lines);

        return new self(
            $method,
            $path,
            [], // server
            [], // query
            [], // body
            [], // cookies
            []  // files
        );
    }

    private function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        return $headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setContainer($container): void
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }
}
