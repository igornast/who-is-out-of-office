<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\UserDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return UserDTO[]
     */
    public function findAll(): array
    {
        $users = parent::findAll();

        return array_map(fn (User $user) => UserDTO::fromEntity($user), $users);
    }

    public function findOneById(string $id): ?UserDTO
    {
        $user = $this->find($id);

        if (!$user instanceof User) {
            return null;
        }

        return UserDTO::fromEntity($user);
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

    public function findUsersWithIncomingBirthdays(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $sql = "
            SELECT *
            FROM user
            WHERE (
                CASE 
                    WHEN DATE_FORMAT(birth_date, '%m-%d') >= DATE_FORMAT(CURRENT_DATE(), '%m-%d')
                        THEN STR_TO_DATE(CONCAT(YEAR(CURRENT_DATE()), '-', DATE_FORMAT(birth_date, '%m-%d')), '%Y-%m-%d')
                    ELSE STR_TO_DATE(CONCAT(YEAR(CURRENT_DATE())+1, '-', DATE_FORMAT(birth_date, '%m-%d')), '%Y-%m-%d')
                END
            ) BETWEEN :start AND :end
            ORDER BY 
                CASE 
                    WHEN DATE_FORMAT(birth_date, '%m-%d') >= DATE_FORMAT(CURRENT_DATE(), '%m-%d')
                        THEN STR_TO_DATE(CONCAT(YEAR(CURRENT_DATE()), '-', DATE_FORMAT(birth_date, '%m-%d')), '%Y-%m-%d')
                    ELSE STR_TO_DATE(CONCAT(YEAR(CURRENT_DATE())+1, '-', DATE_FORMAT(birth_date, '%m-%d')), '%Y-%m-%d')
                END ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':start', $start->format('Y-m-d'));
        $stmt->bindValue(':end', $end->format('Y-m-d'));
        $resultSet = $stmt->executeQuery();
        $rows = $resultSet->fetchAllAssociative();

        $userDTOs = [];
        foreach ($rows as $row) {
            $userDTOs[] = UserDTO::fromArray($row);
        }

        return $userDTOs;
    }

    public function findUserBySlackMemberId(string $slackMemberId): ?UserDTO
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->join('u.slackIntegration', 'si')
            ->where('si.slackMemberId = :slackMemberId')
            ->setParameter('slackMemberId', $slackMemberId);

        $user = $qb->getQuery()->getOneOrNullResult();

        if (!$user instanceof User) {
            return null;
        }

        return UserDTO::fromEntity($user);
    }
}
