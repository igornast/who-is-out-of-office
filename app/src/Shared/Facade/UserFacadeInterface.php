<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Shared\DTO\InvitationDTO;
use App\Shared\DTO\UserDTO;

interface UserFacadeInterface
{
    public function updateUserCurrentLeaveBalance(string $userId, int $number): void;

    /**
     * @return UserDTO[]
     */
    public function getTeamMembersForUserId(string $userId): array;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingBirthdays(): array;

    public function getUserBySlackMemberId(string $slackMemberId): ?UserDTO;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithBirthdaysForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingWorkAnniversaries(): array;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithWorkAnniversariesForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;

    public function acceptUserInvitation(UserInvitationRequestDTO $invitationRequestDTO, InvitationDTO $invitationDTO): void;

    public function getUser(string $userId): ?UserDTO;

    public function resetAbsenceBalance(): void;

    /**
     * @return UserDTO[]
     */
    public function getDirectReports(string $managerId): array;
}
