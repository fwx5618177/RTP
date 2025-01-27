<?php

namespace App\Http;

use App\Logs\Logger;
use App\Utils\Container;

class Request
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_OPTIONS = 'OPTIONS';

    private string $method;
    private string $path;
    private array $queryParams = [];
    private array $bodyParams = [];
    private array $headers = [];
    private array $cookies = [];
    private array $files = [];
    private ?string $rawBody = null;
    private $container;
    private Logger $logger;

    public function __construct(
        string $method,
        ?string $path = '',
        array $query = [],
        array $headers = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $rawBody = null
    ) {
        $this->logger = Container::getInstance()->get(Logger::class);
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->queryParams = $query;
        $this->headers = array_change_key_case(array_merge($headers, $this->extractHeaders($server)), CASE_LOWER);
        $this->cookies = $cookies;
        $this->files = $files;
        $this->rawBody = $rawBody;

        // 解析请求体
        if ($this->rawBody) {
            $this->bodyParams = $this->parseRequestBody();
            $this->logger->debug('Parsed request body', [
                'method' => $this->method,
                'contentType' => $this->getHeader('content-type'),
                'bodyParams' => $this->bodyParams,
            ]);
        }
    }

    private function parseRequestBody(): array
    {
        if ($this->method === self::METHOD_GET || $this->method === self::METHOD_HEAD) {
            return [];
        }

        $contentType = $this->getHeader('content-type') ?? '';

        $this->logger->debug('Parsing request body', [
            'contentType' => $contentType,
            'rawBody' => $this->rawBody,
        ]);

        if (empty($this->rawBody)) {
            return [];
        }

        if (str_contains(strtolower($contentType), 'application/json')) {
            $data = json_decode($this->rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('JSON decode error', ['error' => json_last_error_msg()]);

                return [];
            }

            return $data ?? [];
        }

        if (str_contains(strtolower($contentType), 'application/x-www-form-urlencoded')) {
            parse_str($this->rawBody, $data);

            return $data;
        }

        if (str_contains(strtolower($contentType), 'multipart/form-data')) {
            return $_POST;
        }

        return [];
    }

    public static function createFromGlobals(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $_GET,
            getallheaders() ?: [],
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
            if (empty($line)) {
                break;
            }

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
            $server, // server
            $body // rawBody
        );
    }

    public static function createFromSwoole(\Swoole\Http\Request $swooleRequest): self
    {
        $uri = $swooleRequest->server['request_uri'];
        $queryString = $swooleRequest->server['query_string'] ?? '';
        parse_str($queryString, $queryParams);

        $headers = [];
        foreach ($swooleRequest->header as $name => $value) {
            $headers[$name] = $value;
        }

        $request = new self(
            $swooleRequest->server['request_method'],
            $uri,
            $queryParams,
            $headers,
            $swooleRequest->cookie ?? [],
            $swooleRequest->files ?? [],
            $swooleRequest->server,
            $swooleRequest->rawContent()  // 传入原始请求体
        );

        return $request;
    }

    // HTTP 方法判断
    public function isGet(): bool
    {
        return $this->method === self::METHOD_GET;
    }

    public function isPost(): bool
    {
        return $this->method === self::METHOD_POST;
    }

    public function isPut(): bool
    {
        return $this->method === self::METHOD_PUT;
    }

    public function isPatch(): bool
    {
        return $this->method === self::METHOD_PATCH;
    }

    public function isDelete(): bool
    {
        return $this->method === self::METHOD_DELETE;
    }

    public function isHead(): bool
    {
        return $this->method === self::METHOD_HEAD;
    }

    public function isOptions(): bool
    {
        return $this->method === self::METHOD_OPTIONS;
    }

    // Getters
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
        if ($this->method === self::METHOD_GET || $this->method === self::METHOD_HEAD) {
            return [];
        }

        $contentType = $this->getHeader('content-type') ?? '';

        // 记录请求内容类型和原始数据
        $this->logger->debug('Parsing request body', [
            'contentType' => $contentType,
            'rawBody' => $this->rawBody,
        ]);

        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($this->rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
            $this->logger->error('JSON decode error', [
                'error' => json_last_error_msg(),
                'rawBody' => $this->rawBody,
            ]);
        }

        return [];
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);

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

    public function input(string $key, $default = null)
    {
        return $this->bodyParams[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->bodyParams);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($this->bodyParams[$key]) || isset($this->queryParams[$key]);
    }

    private function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                $name = str_replace('_', '-', $key);
                $headers[$name] = $value;
            }
        }

        return $headers;
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
