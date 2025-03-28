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

    public function save(UserDTO $userDTO): void;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingBirthdays(\DateTimeImmutable $start, \DateTimeImmutable $end): array;
}
