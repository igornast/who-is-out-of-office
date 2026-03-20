<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Doctrine\Entity\UserSlackIntegration;
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

    public function findOneByEmail(string $email): ?UserDTO
    {
        $user = $this->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            return null;
        }

        return UserDTO::fromEntity($user);
    }

    public function update(UserDTO $userDTO): void
    {
        /** @var User $user */
        $user = $this->find($userDTO->id);

        $user->firstName = $userDTO->firstName;
        $user->lastName = $userDTO->lastName;
        $user->email = $userDTO->email;
        $user->roles = $userDTO->roles;
        $user->annualLeaveAllowance = $userDTO->annualLeaveAllowance;
        $user->currentLeaveBalance = $userDTO->currentLeaveBalance;
        $user->isActive = $userDTO->isActive;
        $user->isEmailNotificationsEnabled = $userDTO->isEmailNotificationsEnabled;
        $user->birthDate = $userDTO->birthDate;
        $user->absenceBalanceResetDay = $userDTO->absenceBalanceResetDay;

        if (null !== $userDTO->password) {
            $user->password = $userDTO->password;
        }

        $user->themePreference = $userDTO->themePreference;
        $user->palettePreference = $userDTO->palettePreference;
        $user->icalHashSalt = $userDTO->icalHashSalt;
        $user->subdivisionCode = $userDTO->subdivisionCode;

        if (null !== $userDTO->managerId) {
            $user->manager = $this->find($userDTO->managerId);
        } else {
            $user->manager = null;
        }

        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    public function updateThemePreference(string $userId, string $theme, string $palette): void
    {
        /** @var User $user */
        $user = $this->find($userId);

        $user->themePreference = $theme;
        $user->palettePreference = $palette;

        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    public function updatePassword(string $userId, string $hashedPassword): void
    {
        /** @var User $user */
        $user = $this->find($userId);

        $user->password = $hashedPassword;

        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    /**
     * @return UserDTO[]
     */
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
            AND is_active = 1
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

    /**
     * @return UserDTO[]
     */
    public function findUsersWithIncomingWorkAnniversaries(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $sql = "
            SELECT *
            FROM user
            WHERE (
                CASE
                    WHEN DATE_FORMAT(contract_started_at, '%m-%d') >= DATE_FORMAT(:start, '%m-%d')
                        THEN STR_TO_DATE(CONCAT(YEAR(:start), '-', DATE_FORMAT(contract_started_at, '%m-%d')), '%Y-%m-%d')
                    ELSE STR_TO_DATE(CONCAT(YEAR(:start)+1, '-', DATE_FORMAT(contract_started_at, '%m-%d')), '%Y-%m-%d')
                END
            ) BETWEEN :start AND :end
            AND is_active = 1
            AND celebrate_work_anniversary = 1
            AND contract_started_at IS NOT NULL
            ORDER BY
                CASE
                    WHEN DATE_FORMAT(contract_started_at, '%m-%d') >= DATE_FORMAT(:start, '%m-%d')
                        THEN STR_TO_DATE(CONCAT(YEAR(:start), '-', DATE_FORMAT(contract_started_at, '%m-%d')), '%Y-%m-%d')
                    ELSE STR_TO_DATE(CONCAT(YEAR(:start)+1, '-', DATE_FORMAT(contract_started_at, '%m-%d')), '%Y-%m-%d')
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

    /**
     * @return UserDTO[]
     */
    public function findUsersWithBalanceResetToday(): array
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $sql = '
            SELECT *
            FROM user
            WHERE absence_balance_reset_day <= CURRENT_DATE()
            AND is_active = 1
        ';

        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $rows = $resultSet->fetchAllAssociative();

        $userDTOs = [];
        foreach ($rows as $row) {
            $userDTOs[] = UserDTO::fromArray($row);
        }

        return $userDTOs;
    }

    /**
     * @return UserDTO[]
     */
    public function findByManagerId(string $managerId): array
    {
        $users = $this->findBy(['manager' => $managerId, 'isActive' => true]);

        return array_map(fn (User $user) => UserDTO::fromEntity($user), $users);
    }

    public function updateIcalHashSalt(string $userId, string $salt): void
    {
        /** @var User $user */
        $user = $this->find($userId);

        $user->icalHashSalt = $salt;

        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    public function updateSlackMemberId(string $userId, string $slackMemberId): void
    {
        /** @var User $user */
        $user = $this->find($userId);

        if (null === $user->slackIntegration) {
            $user->slackIntegration = new UserSlackIntegration(user: $user, slackMemberId: $slackMemberId);
        } else {
            $user->slackIntegration->slackMemberId = $slackMemberId;
        }

        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    public function removeSlackIntegration(string $userId): void
    {
        /** @var User $user */
        $user = $this->find($userId);

        if (null === $user->slackIntegration) {
            return;
        }

        $em = $this->getEntityManager();
        $em->remove($user->slackIntegration);
        $user->slackIntegration = null;
        $em->flush();
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
