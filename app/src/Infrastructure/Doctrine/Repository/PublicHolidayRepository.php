<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Holiday>
 */
class PublicHolidayRepository extends ServiceEntityRepository implements PublicHolidayRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Holiday::class);
    }

    /**
     * @return PublicHolidayDTO[]
     */
    public function findBetweenDatesForCountryCode(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode): array
    {
        $qb = $this->createQueryBuilder('h');

        /** @var Holiday[] $items */
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

    /**
     * @return array{string, UserPublicHolidaysDTO}
     */
    public function findBetweenDatesGroupedByUser(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $query = <<<SQL
            SELECT h.id, h.date, h.description, hc.country_code,
                   u.id as user_id, u.first_name, u.last_name, u.email, u.roles, u.working_days, 
                   u.annual_leave_allowance, u.current_leave_balance, u.is_active, u.profile_image_url, u.birth_date
            FROM holiday h
            INNER JOIN holiday_calendar hc ON h.holiday_calendar_id = hc.id
            INNER JOIN user u ON u.holiday_calendar_id = hc.id
            WHERE h.date BETWEEN :startDate AND :endDate
SQL;

        $em = $this->getEntityManager();
        /** @var array{int, array{string, string|int}} $items */
        $items = $em
            ->getConnection()
            ->prepare($query)
            ->executeQuery([
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ])
            ->fetchAllAssociative();

        $grouped = [];
        foreach ($items as $holidayData) {
            if(!isset($holidayData['user_id'])) {
                continue;
            }

            $grouped[$holidayData['user_id']][] = $holidayData;
        }

        return array_map(fn (array $groupedData) => UserPublicHolidaysDTO::createFromGroupedArray($groupedData), $grouped);
    }
}
