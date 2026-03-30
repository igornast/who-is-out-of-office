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
    public function findBetweenDatesForCountryCode(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode, ?string $subdivisionCode = null): array
    {
        $sql = <<<'SQL'
            SELECT h.id, h.date, h.description, h.is_global, h.counties, hc.country_code
            FROM holiday h
            INNER JOIN holiday_calendar hc ON h.holiday_calendar_id = hc.id
            WHERE h.date BETWEEN :startDate AND :endDate
              AND hc.country_code = :countryCode
        SQL;

        $params = [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'countryCode' => $countryCode,
        ];

        if (null !== $subdivisionCode) {
            $sql .= ' AND (h.is_global = 1 OR JSON_CONTAINS(h.counties, JSON_QUOTE(:subdivisionCode)))';
            $params['subdivisionCode'] = $subdivisionCode;
        }

        $sql .= ' ORDER BY h.date ASC';

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $rows = $stmt->executeQuery()->fetchAllAssociative();

        return array_map(fn (array $row) => PublicHolidayDTO::fromArray($row), $rows);
    }

    /**
     * @return array{string, UserPublicHolidaysDTO}
     */
    public function findBetweenDatesGroupedByUser(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $sql = <<<SQL
            SELECT h.id, h.date, h.description, h.is_global, h.counties, hc.country_code,
                   u.id as user_id, u.first_name, u.last_name, u.email, u.roles, u.working_days,
                   u.annual_leave_allowance, u.current_leave_balance, u.is_active, u.profile_image_url, u.birth_date,
                   u.created_at, u.absence_balance_reset_day, u.subdivision_code
            FROM holiday h
            INNER JOIN holiday_calendar hc ON h.holiday_calendar_id = hc.id
            INNER JOIN user u ON u.holiday_calendar_id = hc.id
            WHERE h.date BETWEEN :startDate AND :endDate
              AND (h.is_global = 1 OR u.subdivision_code IS NULL OR JSON_CONTAINS(h.counties, JSON_QUOTE(u.subdivision_code)))
SQL;

        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':startDate', $startDate->format('Y-m-d'));
        $stmt->bindValue(':endDate', $endDate->format('Y-m-d'));
        $resultSet = $stmt->executeQuery();
        $rows = $resultSet->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $holidayData) {
            if (!isset($holidayData['user_id'])) {
                continue;
            }

            $grouped[$holidayData['user_id']][] = $holidayData;
        }

        return array_map(fn (array $groupedData) => UserPublicHolidaysDTO::createFromGroupedArray($groupedData), $grouped);
    }

    /**
     * @return array<string, string[]>
     */
    public function findDistinctSubdivisionsGroupedByCalendar(): array
    {
        $sql = <<<SQL
            SELECT hc.id AS calendar_id, jt.subdivision
            FROM holiday h
            INNER JOIN holiday_calendar hc ON h.holiday_calendar_id = hc.id
            CROSS JOIN JSON_TABLE(h.counties, '$[*]' COLUMNS (subdivision VARCHAR(10) PATH '$')) AS jt
            WHERE h.counties IS NOT NULL
            GROUP BY hc.id, jt.subdivision
            ORDER BY hc.id, jt.subdivision
        SQL;

        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->executeQuery($sql)->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['calendar_id']][] = $row['subdivision'];
        }

        return $grouped;
    }
}
