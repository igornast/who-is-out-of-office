<?php

declare(strict_types=1);

namespace App\Module\Holiday\Service;

use App\Shared\DTO\Holiday\HolidayReconciliationDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;

class HolidayImportReconciler
{
    /**
     * @param list<PublicHolidayDTO> $existing
     * @param list<PublicHolidayDTO> $imported
     */
    public function reconcile(array $existing, array $imported, \DateTimeImmutable $today): HolidayReconciliationDTO
    {
        $pool = [];
        foreach ($existing as $index => $holiday) {
            $pool[$this->key($holiday)][] = $index;
        }

        $unmatchedImported = [];
        $matchedExistingIndexes = [];
        foreach ($imported as $index => $holiday) {
            $key = $this->key($holiday);
            if ([] !== ($pool[$key] ?? [])) {
                $matchedExistingIndexes[array_pop($pool[$key])] = true;
                continue;
            }

            $unmatchedImported[$index] = $holiday;
        }

        $unmatchedExisting = [];
        foreach ($existing as $index => $holiday) {
            if (!isset($matchedExistingIndexes[$index])) {
                $unmatchedExisting[$index] = $holiday;
            }
        }

        $toUpdate = [];
        $pairedExistingIndexes = [];
        $pairedImportedIndexes = [];

        $existingByName = $this->groupByDateAndDescription($unmatchedExisting);
        $importedByName = $this->groupByDateAndDescription($unmatchedImported);
        foreach ($existingByName as $name => $existingForName) {
            $importedForName = $importedByName[$name] ?? [];
            $existingIndexes = array_keys($existingForName);
            $importedIndexes = array_keys($importedForName);
            $pairCount = min(count($existingIndexes), count($importedIndexes));

            for ($pairIndex = 0; $pairIndex < $pairCount; ++$pairIndex) {
                $existingIndex = $existingIndexes[$pairIndex];
                $importedIndex = $importedIndexes[$pairIndex];
                $existingHoliday = $existingForName[$existingIndex];
                $importedHoliday = $importedForName[$importedIndex];
                $toUpdate[$existingHoliday->id] = $importedHoliday;
                $pairedExistingIndexes[$existingIndex] = true;
                $pairedImportedIndexes[$importedIndex] = true;
            }
        }

        $existingByDate = $this->groupByDate($unmatchedExisting, $pairedExistingIndexes);
        $importedByDate = $this->groupByDate($unmatchedImported, $pairedImportedIndexes);
        foreach ($existingByDate as $date => $existingForDate) {
            $importedForDate = $importedByDate[$date] ?? [];
            if (1 !== count($existingForDate) || 1 !== count($importedForDate)) {
                continue;
            }

            $existingIndex = array_key_first($existingForDate);
            $existingHoliday = $existingForDate[$existingIndex];
            $importedIndex = array_key_first($importedForDate);
            $importedHoliday = $importedForDate[$importedIndex];
            $toUpdate[$existingHoliday->id] = $importedHoliday;
            $pairedExistingIndexes[$existingIndex] = true;
            $pairedImportedIndexes[$importedIndex] = true;
        }

        $toInsert = [];
        foreach ($unmatchedImported as $index => $holiday) {
            if (!isset($pairedImportedIndexes[$index])) {
                $toInsert[] = $holiday;
            }
        }

        $todayString = $today->format('Y-m-d');
        $toDelete = [];
        foreach ($unmatchedExisting as $index => $holiday) {
            if (!isset($pairedExistingIndexes[$index]) && $holiday->date->format('Y-m-d') >= $todayString) {
                $toDelete[] = $holiday->id;
            }
        }

        return new HolidayReconciliationDTO($toUpdate, $toInsert, $toDelete);
    }

    private function key(PublicHolidayDTO $holiday): string
    {
        $counties = $holiday->counties ?? [];
        sort($counties);

        return sprintf(
            '%s|%s|%s|%s',
            $holiday->date->format('Y-m-d'),
            $holiday->description,
            $holiday->isGlobal ? '1' : '0',
            json_encode($counties, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<int, PublicHolidayDTO> $holidays
     * @param array<int, bool>             $excludedIndexes
     *
     * @return array<string, array<int, PublicHolidayDTO>>
     */
    private function groupByDate(array $holidays, array $excludedIndexes): array
    {
        $grouped = [];
        foreach ($holidays as $index => $holiday) {
            if (isset($excludedIndexes[$index])) {
                continue;
            }

            $grouped[$holiday->date->format('Y-m-d')][$index] = $holiday;
        }

        return $grouped;
    }

    /**
     * @param array<int, PublicHolidayDTO> $holidays
     *
     * @return array<string, array<int, PublicHolidayDTO>>
     */
    private function groupByDateAndDescription(array $holidays): array
    {
        $grouped = [];
        foreach ($holidays as $index => $holiday) {
            $key = sprintf('%s|%s', $holiday->date->format('Y-m-d'), $holiday->description);
            $grouped[$key][$index] = $holiday;
        }

        return $grouped;
    }
}
