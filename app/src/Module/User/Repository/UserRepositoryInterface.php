<?php

declare(strict_types=1);

namespace App\Module\User\Repository;

use App\Shared\DTO\UserDTO;

interface UserRepositoryInterface
{
    /**
     * @return UserDTO[]
     */
    public function findAll(): array;

    public function findOneById(string $id): ?UserDTO;

    public function update(UserDTO $userDTO): void;

    /**
     * @return UserDTO[]
     */
    public function findUsersWithIncomingBirthdays(\DateTimeImmutable $start, \DateTimeImmutable $end): array;

    /**
     * @return UserDTO[]
     */
    public function findUsersWithIncomingWorkAnniversaries(\DateTimeImmutable $start, \DateTimeImmutable $end): array;

    public function findUserBySlackMemberId(string $slackMemberId): ?UserDTO;

    /**
     * @return UserDTO[]
     */
    public function findUsersWithBalanceResetToday(): array;

    /**
     * @return UserDTO[]
     */
    public function findByManagerId(string $managerId): array;

    public function updateThemePreference(string $userId, string $theme, string $palette): void;

    public function updatePassword(string $userId, string $hashedPassword): void;

    public function updateIcalHashSalt(string $userId, string $salt): void;

    public function updateSlackMemberId(string $userId, string $slackMemberId): void;

    public function removeSlackIntegration(string $userId): void;

}
