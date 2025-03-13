<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class User
{
    use TimestampableTrait;

    public function __construct(
        private int $id,
        private string $firstName,
        private string $lastName,
        private string $email,
        private string $role,
        private int $annualLeaveAllowance = 24,
        private Collection $leaveRequests = new ArrayCollection(),
    ) {
        $this->initializeTimestamps();
    }
}
