<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
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

    public function upsertByCountryCode(PublicHolidayCalendarDTO $calendarDTO): void
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

        $calendarEntity->countryName = $calendarDTO->countryName;
        $calendarEntity->countryCode = $calendarDTO->countryCode;
        array_map(
            function (PublicHolidayDTO $holidayDTO) use ($calendarEntity) {
                $calendarEntity->holidays
                    ->add(new Holiday(
                        id: Uuid::fromString($holidayDTO->id),
                        description: $holidayDTO->description,
                        date: $holidayDTO->date,
                        holidayCalendar: $calendarEntity
                    ));
            },
            $calendarDTO->holidays
        );

        $entityManager->persist($calendarEntity);
        $entityManager->flush();
    }
}
