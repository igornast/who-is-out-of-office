<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<HolidayCalendar>
 */
class PublicHolidayCalendarRepository extends ServiceEntityRepository implements PublicHolidayCalendarRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HolidayCalendar::class);
    }

    public function findByCountryCode(string $countryCode): ?PublicHolidayCalendarDTO
    {
        $item = $this->findOneBy(['countryCode' => $countryCode]);

        if (null === $item) {
            return null;
        }

        return PublicHolidayCalendarDTO::fromEntity($item);
    }

    public function upsertByCountryCode(PublicHolidayCalendarDTO $calendarDTO, ?int $year = null): void
    {
        /** @var ?HolidayCalendar $calendarEntity */
        $calendarEntity = $this->findOneBy(['countryCode' => $calendarDTO->countryCode]);

        $entityManager = $this->getEntityManager();
        if (null === $calendarEntity) {
            $calendarEntity = new HolidayCalendar(
                id: Uuid::uuid4(),
                countryCode: $calendarDTO->countryCode,
                countryName: $calendarDTO->countryName
            );
        }

        if (null !== $year) {
            $this->deleteHolidaysForYear($calendarEntity->id->toString(), $year);
            $calendarEntity->lastSyncedYear = $year;
        }

        $calendarEntity->countryName = $calendarDTO->countryName;
        $calendarEntity->countryCode = $calendarDTO->countryCode;
        foreach ($calendarDTO->holidays as $holidayDTO) {
            $calendarEntity->holidays->add(new Holiday(
                id: Uuid::fromString($holidayDTO->id),
                description: $holidayDTO->description,
                date: $holidayDTO->date,
                holidayCalendar: $calendarEntity,
                isGlobal: $holidayDTO->isGlobal,
                counties: $holidayDTO->counties,
            ));
        }

        $entityManager->persist($calendarEntity);
        $entityManager->flush();
    }

    /**
     * @return PublicHolidayCalendarDTO[]
     */
    public function findAll(): array
    {
        /** @var HolidayCalendar[] $calendars */
        $calendars = parent::findAll();

        return array_map(
            fn (HolidayCalendar $calendar) => PublicHolidayCalendarDTO::fromEntity($calendar),
            $calendars
        );
    }

    public function updateActive(string $calendarId, bool $isActive): void
    {
        $entityManager = $this->getEntityManager();
        $calendar = $entityManager->find(HolidayCalendar::class, Uuid::fromString($calendarId));

        if (null === $calendar) {
            throw new \RuntimeException(sprintf('Holiday calendar %s not found', $calendarId));
        }

        $calendar->isActive = $isActive;
        $entityManager->flush();
    }

    public function delete(string $calendarId): void
    {
        $entityManager = $this->getEntityManager();
        $calendar = $entityManager->find(HolidayCalendar::class, Uuid::fromString($calendarId));

        if (null === $calendar) {
            throw new \RuntimeException(sprintf('Holiday calendar %s not found', $calendarId));
        }

        $entityManager->remove($calendar);
        $entityManager->flush();
    }

    public function hasAssignedUsers(string $calendarId): bool
    {
        $count = (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(u.id) FROM App\Infrastructure\Doctrine\Entity\User u WHERE u.holidayCalendar = :calendarId')
            ->setParameter('calendarId', Uuid::fromString($calendarId))
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function deleteHolidaysForYear(string $calendarId, int $year): void
    {
        $startDate = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $endDate = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Infrastructure\Doctrine\Entity\Holiday h WHERE h.holidayCalendar = :calendarId AND h.date >= :startDate AND h.date <= :endDate')
            ->setParameter('calendarId', Uuid::fromString($calendarId))
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->execute();
    }
}
