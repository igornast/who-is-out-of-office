<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\DTO\PasswordHashUserDTO;
use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\InvitationDTO;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AcceptUserInvitationCommandHandler
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function handle(UserInvitationRequestDTO $invitationRequestDTO, InvitationDTO $invitationDTO): void
    {
        $userDTO = $invitationDTO->user;
        $userDTO->firstName = $invitationRequestDTO->firstName;
        $userDTO->lastName = $invitationRequestDTO->lastName;
        $userDTO->birthDate = $invitationRequestDTO->birthdate;
        $userDTO->password = $this->passwordHasher->hashPassword(
            new PasswordHashUserDTO($userDTO->email, $userDTO->roles),
            $invitationRequestDTO->password
        );
        $userDTO->isActive = true;

        $this->userRepository->update($userDTO);
        $this->invitationRepository->remove($invitationDTO);
    }
}
