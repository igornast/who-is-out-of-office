<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\UuidInterface;

class User
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public string $role,
        public int $annualLeaveAllowance = 24,
        public ?Collection $leaveRequests = new ArrayCollection(),
    ) {
        $this->initializeTimestamps();
    }
}
