<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\DTO\UserDTO;

class UserTransformer
{
    public function toDTO(User $user): UserDTO
    {
        return new UserDTO(
            $user->id->toString(),
            $user->firstName,
            $user->lastName,
            $user->email,
            $user->roles,
            $user->annualLeaveAllowance
        );
    }
}