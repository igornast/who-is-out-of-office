<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class LeaveType
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public string $name,
        public ?string $description = null,
    ) {
        $this->initializeTimestamps();
    }
}
