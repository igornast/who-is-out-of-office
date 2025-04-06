<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\UserDTO;

interface UserFacadeInterface
{
    public function updateUserCurrentLeaveBalance(string $userId, int $number): void;

    public function getMyTeamUsers(string $userId): array;

    public function getUsersWithIncomingBirthdays(): array;

    public function getUserBySlackMemberId(string $slackMemberId): ?UserDTO;
}
