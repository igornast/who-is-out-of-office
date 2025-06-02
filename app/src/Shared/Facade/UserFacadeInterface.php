<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Shared\DTO\InvitationDTO;
use App\Shared\DTO\UserDTO;

interface UserFacadeInterface
{
    public function updateUserCurrentLeaveBalance(string $userId, int $number): void;

    public function getMyTeamUsers(string $userId): array;

    public function getUsersWithIncomingBirthdays(): array;

    public function getUserBySlackMemberId(string $slackMemberId): ?UserDTO;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithBirthdaysForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;

    public function acceptUserInvitation(UserInvitationRequestDTO $invitationRequestDTO, InvitationDTO $invitationDTO): void;
}
