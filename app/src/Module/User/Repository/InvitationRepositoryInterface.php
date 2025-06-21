<?php

declare(strict_types=1);

namespace App\Module\User\Repository;

use App\Shared\DTO\InvitationDTO;

interface InvitationRepositoryInterface
{
    public function findOneByToken(string $token): ?InvitationDTO;

    public function remove(InvitationDTO $invitationDTO): void;

    /**
     * @param array<string,mixed>       $criteria
     * @param array<string, mixed>|null $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;
}
