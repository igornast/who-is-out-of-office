<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;

class LeaveType
{
    use TimestampableTrait;

    public function __construct(
        private string $name = '',
        private ?int $id = null,
        private ?string $description = null,
    ) {
        $this->initializeTimestamps();
    }
}
