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
}
