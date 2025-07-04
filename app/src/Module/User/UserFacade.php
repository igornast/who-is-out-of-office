<?php

declare(strict_types=1);

namespace App\Module\User;

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\UseCase\Command\AcceptUserInvitationCommandHandler;
use App\Module\User\UseCase\Command\UpdateCurrentLeaveBalanceCommandHandler;
use App\Module\User\UseCase\Query\GetMyTeamUsersQueryHandler;
use App\Module\User\UseCase\Query\GetUserByIdQueryHandler;
use App\Module\User\UseCase\Query\GetUserBySlackMemberIdQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithBirthdaysForDates;
use App\Module\User\UseCase\Query\GetUsersWithIncomingBirthdaysQueryHandler;
use App\Shared\DTO\InvitationDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\UserFacadeInterface;

final class UserFacade implements UserFacadeInterface
{
    public function __construct(
        private readonly UpdateCurrentLeaveBalanceCommandHandler $updateCurrentLeaveBalanceHandler,
        private readonly GetMyTeamUsersQueryHandler $getMyTeamUsersHandler,
        private readonly GetUsersWithIncomingBirthdaysQueryHandler $getUsersWithIncomingBirthdaysHandler,
        private readonly GetUserBySlackMemberIdQueryHandler $getUserBySlackMemberIdQueryHandler,
        private readonly GetUserByIdQueryHandler $getUserByIdQueryHandler,
        private readonly GetUsersWithBirthdaysForDates $getUsersWithBirthdaysForDatesHandler,
        private readonly AcceptUserInvitationCommandHandler $acceptInvitationHandler,
    ) {
    }

    public function updateUserCurrentLeaveBalance(string $userId, int $number): void
    {
        $this->updateCurrentLeaveBalanceHandler->handle($userId, $number);
    }

    /**
     * @return UserDTO[]
     */
    public function getTeamMembersForUserId(string $userId): array
    {
        return $this->getMyTeamUsersHandler->handle($userId);
    }

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingBirthdays(): array
    {
        return $this->getUsersWithIncomingBirthdaysHandler->handle();
    }

    public function getUserBySlackMemberId(string $slackMemberId): ?UserDTO
    {
        return $this->getUserBySlackMemberIdQueryHandler->handle($slackMemberId);
    }

    /**
     * @return UserDTO[]
     */
    public function getUsersWithBirthdaysForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->getUsersWithBirthdaysForDatesHandler->handle($startDate, $endDate);
    }

    public function acceptUserInvitation(UserInvitationRequestDTO $invitationRequestDTO, InvitationDTO $invitationDTO): void
    {
        $this->acceptInvitationHandler->handle($invitationRequestDTO, $invitationDTO);
    }

    public function getUser(string $userId): ?UserDTO
    {
        return $this->getUserByIdQueryHandler->handle($userId);
    }
}
