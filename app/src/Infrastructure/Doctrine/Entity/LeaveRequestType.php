<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class LeaveRequestType
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public bool $isAffectingBalance,
        public string $name,
        public string $backgroundColor,
        public string $borderColor,
        public string $textColor,
        public string $icon,
    ) {
        $this->initializeTimestamps();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
