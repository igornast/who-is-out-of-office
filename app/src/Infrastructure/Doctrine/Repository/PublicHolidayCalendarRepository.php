<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Shared\DTO\Holiday\HolidayReconciliationDTO;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
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

    /**
     * @return list<PublicHolidayDTO>
     */
    public function findHolidaysForYear(string $countryCode, int $year): array
    {
        /** @var ?HolidayCalendar $calendarEntity */
        $calendarEntity = $this->findOneBy(['countryCode' => $countryCode]);
        if (null === $calendarEntity) {
            return [];
        }

        return array_filter(
            $calendarEntity->holidays->toArray(),
            fn (Holiday $holiday): bool => $holiday->date->format('Y') === (string) $year,
        )
            |> (fn (array $holidays): array => array_map(PublicHolidayDTO::fromEntity(...), $holidays))
            |> array_values(...);
    }

    public function applyHolidayReconciliation(PublicHolidayCalendarDTO $calendarDTO, int $year, HolidayReconciliationDTO $reconciliation): void
    {
        /** @var ?HolidayCalendar $calendarEntity */
        $calendarEntity = $this->findOneBy(['countryCode' => $calendarDTO->countryCode]);
        if (null === $calendarEntity) {
            $calendarEntity = new HolidayCalendar(
                id: Uuid::uuid4(),
                countryCode: $calendarDTO->countryCode,
                countryName: $calendarDTO->countryName,
            );
        }

        $calendarEntity->countryName = $calendarDTO->countryName;
        $calendarEntity->countryCode = $calendarDTO->countryCode;
        $calendarEntity->lastSyncedYear = $year;
        $entityManager = $this->getEntityManager();

        foreach ($reconciliation->toUpdate as $holidayId => $holidayDTO) {
            $holiday = $entityManager->find(Holiday::class, Uuid::fromString($holidayId));
            if (null === $holiday) {
                continue;
            }

            $holiday->description = $holidayDTO->description;
            $holiday->isGlobal = $holidayDTO->isGlobal;
            $holiday->counties = $holidayDTO->counties;
        }

        foreach ($reconciliation->toInsert as $holidayDTO) {
            $calendarEntity->holidays->add(new Holiday(
                id: Uuid::fromString($holidayDTO->id),
                description: $holidayDTO->description,
                date: $holidayDTO->date,
                holidayCalendar: $calendarEntity,
                isGlobal: $holidayDTO->isGlobal,
                counties: $holidayDTO->counties,
            ));
        }

        foreach ($reconciliation->toDelete as $holidayId) {
            $holiday = $entityManager->find(Holiday::class, Uuid::fromString($holidayId));
            if (null !== $holiday) {
                $calendarEntity->holidays->removeElement($holiday);
            }
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

    /**
     * @param list<string> $countryCodes
     *
     * @return PublicHolidayCalendarDTO[]
     */
    public function findActiveByCountryCodes(array $countryCodes): array
    {
        if ([] === $countryCodes) {
            return [];
        }

        $items = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->andWhere('c.countryCode IN (:codes)')
            ->setParameter('active', true)
            ->setParameter('codes', array_values(array_unique($countryCodes)))
            ->orderBy('c.countryName', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (HolidayCalendar $c) => PublicHolidayCalendarDTO::fromEntity($c), $items);
    }
}
