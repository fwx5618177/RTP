<?php

namespace App\Entity;

use App\Logs\Logger;

abstract class BaseEntity
{
    protected Logger $logger;

    public function __construct()
    {
        $this->logger = Logger::getInstance(static::class);
    }

    public function logOperation(string $operation): void
    {
        $entityType = static::class;
        $identifier = $this->id ?? 'new';

        $context = [
            'entity' => $entityType,
            'identifier' => $identifier,
            'operation' => $operation,
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->logger->info(
            sprintf('%s entity %s was %s', $entityType, $identifier, $operation),
            $context
        );
    }
}
