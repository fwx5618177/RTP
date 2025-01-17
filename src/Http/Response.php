<?php

namespace App\Http;

class Response
{
    private int $statusCode;
    private array $headers;
    private $body;

    public function __construct(int $statusCode, array $headers = [], $body = null)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        if ($this->body !== null) {
            echo is_array($this->body) ? json_encode($this->body) : $this->body;
        }
    }
}
