<?php

namespace App\Http;

class Response
{
    private int $statusCode;
    private array $headers;
    private $body;

    public function __construct(array|string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = is_array($body) ? json_encode($body) : $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function status(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function body(array|string $body): self
    {
        $this->body = is_array($body) ? json_encode($body) : $body;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }

    public function __toString(): string
    {
        $response = "HTTP/1.1 {$this->statusCode}\r\n";

        // Add headers
        foreach ($this->headers as $name => $value) {
            $response .= "{$name}: {$value}\r\n";
        }

        // Add content length if not set
        if (!isset($this->headers['Content-Length'])) {
            $response .= "Content-Length: " . strlen($this->body) . "\r\n";
        }

        // End headers
        $response .= "\r\n";

        // Add body
        $response .= $this->body;

        return $response;
    }
}
