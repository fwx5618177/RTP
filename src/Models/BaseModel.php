<?php

declare(strict_types=1);

namespace App\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
abstract class BaseModel implements ModelInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }
}
