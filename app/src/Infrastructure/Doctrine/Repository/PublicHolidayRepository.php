<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PublicHolidayRepository extends ServiceEntityRepository implements PublicHolidayRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Holiday::class);
    }

    public function findBetweenDatesForCountryCode(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode): array
    {
        $qb = $this->createQueryBuilder('h');

        $items = $qb->join('h.holidayCalendar', 'hc')
            ->where('h.date BETWEEN :start AND :end')
            ->andWhere('hc.countryCode = :countryCode')
            ->setParameter('start', $startDate->format('Y-m-d'))
            ->setParameter('end', $endDate->format('Y-m-d'))
            ->setParameter('countryCode', $countryCode)
            ->orderBy('h.date', 'ASC')
            ->getQuery()
            ->getResult();


        return array_map(fn (Holiday $holiday) => PublicHolidayDTO::fromEntity($holiday), $items);
    }
}
