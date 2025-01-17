<?php

namespace App\Http;

class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $bodyParams;
    private array $headers;

    public function __construct(
        string $method,
        string $path,
        array $queryParams = [],
        array $bodyParams = [],
        array $headers = []
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
        $this->headers = $headers;
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
}
