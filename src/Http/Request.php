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
        ?string $path = '',
        array $query = [],
        array $headers = [],
        array $cookies = [],
        array $files = [],
        array $server = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->queryParams = $query;
        $this->bodyParams = [];
        $this->headers = array_merge($headers, $this->extractHeaders($server));
        $this->cookies = $cookies;
        $this->files = $files;
    }

    public static function createFromGlobals(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $_GET,
            [], // headers
            $_COOKIE,
            $_FILES,
            $_SERVER
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
        $server = [];
        while ($line = array_shift($lines)) {
            if (empty($line)) break;

            // 解析header行
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // 转换为$_SERVER格式
                $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
                $server[$serverKey] = $value;

                // 同时保留原始header
                $headers[$name] = $value;
            }
        }

        // Parse body
        $body = implode("\r\n", $lines);

        return new self(
            $method,
            $path,
            [], // query
            $headers, // headers
            [], // cookies
            [], // files
            $server // server
        );
    }

    private function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                // 处理标准HTTP头
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            } elseif (in_array($key, [
                'CONTENT_TYPE',
                'CONTENT_LENGTH',
                'CONTENT_MD5',
            ])) {
                // 处理特殊内容头
                $headers[str_replace('_', '-', $key)] = $value;
            }
        }

        // 从apache_request_headers()获取完整header（如果可用）
        if (function_exists('apache_request_headers')) {
            $headers = array_merge($headers, apache_request_headers());
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

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
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
