<?php

declare(strict_types=1);

namespace App\Shared\Facade;

interface UserFacadeInterface
{
    public function updateUserCurrentLeaveBalance(string $userId, int $number): void;
}
