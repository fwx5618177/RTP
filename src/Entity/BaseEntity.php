<?php

namespace App\Entity;

use App\Interfaces\ModelInterface;
use App\Logs\Logger;
use App\Utils\Container;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class BaseEntity implements ModelInterface
{
    protected ?Logger $logger = null;

    public function __construct()
    {
        $this->initLogger();
    }

    public function initLogger(): void
    {
        if ($this->logger === null) {
            $this->logger = Container::getInstance()->get(Logger::class);
        }
    }

    public function logOperation(string $operation, array $context = []): void
    {
        $this->initLogger();
        $this->logger->info(sprintf('%s %s', get_class($this), $operation), $context);
    }
}
