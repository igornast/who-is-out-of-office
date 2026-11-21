<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Service;

use App\Shared\DTO\Holiday\PublicHolidayDTO;

class HolidayStatusTextBuilder
{
    public const MAX_LENGTH = 100;

    private const ELLIPSIS = '…';

    /**
     * @param list<PublicHolidayDTO> $holidays
     *
     * @return list<PublicHolidayDTO>
     */
    public function sortForStatus(array $holidays): array
    {
        usort($holidays, static function (PublicHolidayDTO $first, PublicHolidayDTO $second): int {
            if ($first->isGlobal !== $second->isGlobal) {
                return $first->isGlobal ? -1 : 1;
            }

            return strcmp($first->description, $second->description);
        });

        return $holidays;
    }

    /**
     * @param list<PublicHolidayDTO> $sortedHolidays
     */
    public function buildText(array $sortedHolidays): string
    {
        $names = [];

        foreach ($sortedHolidays as $holiday) {
            if (!in_array($holiday->description, $names, true)) {
                $names[] = $holiday->description;
            }
        }

        $full = implode(', ', $names);
        if (mb_strlen($full) <= self::MAX_LENGTH) {
            return $full;
        }

        $kept = '';
        foreach ($names as $name) {
            $candidate = '' === $kept ? $name : sprintf('%s, %s', $kept, $name);
            if (mb_strlen(sprintf('%s%s', $candidate, self::ELLIPSIS)) > self::MAX_LENGTH) {
                break;
            }

            $kept = $candidate;
        }

        return '' === $kept
            ? sprintf('%s%s', mb_substr($names[0], 0, self::MAX_LENGTH - 1), self::ELLIPSIS)
            : sprintf('%s%s', $kept, self::ELLIPSIS);
    }
}
