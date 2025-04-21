<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Shared\DTO\UserDTO;

class GetUserBySlackMemberIdQueryHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function handle(string $slackMemberId): ?UserDTO
    {
        return $this->userRepository->findUserBySlackMemberId($slackMemberId);
    }
}
