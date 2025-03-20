<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Transformer\UserTransformer;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\UserDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface

{
    public function __construct(ManagerRegistry $registry, private readonly UserTransformer $userTransformer)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return UserDTO[]
     */
    public function findAll(): array
    {
        $users = parent::findAll();

        return array_map(fn(User $user) => $this->userTransformer->toDTO($user), $users);
    }
}