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

        return array_map(fn (User $user) => $this->userTransformer->toDTO($user), $users);
    }

    public function findOneById(string $id): ?UserDTO
    {
        $user = $this->find($id);

        if (!$user instanceof User) {
            return null;
        }

        return $this->userTransformer->toDTO($user);
    }

    public function save(UserDTO $userDTO): void
    {
        /** @var User $user */
        $user = $this->find($userDTO->id);

        $user->firstName = $userDTO->firstName;
        $user->lastName = $userDTO->lastName;
        $user->email = $userDTO->email;
        $user->roles = $userDTO->roles;
        $user->annualLeaveAllowance = $userDTO->annualLeaveAllowance;
        $user->currentLeaveBalance = $userDTO->currentLeaveBalance;

        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }
}
