<?php

declare(strict_types=1);

namespace App\Shared\DTO\Holiday;

readonly class HolidayReconciliationDTO
{
    /**
     * @param array<string, PublicHolidayDTO> $toUpdate
     * @param list<PublicHolidayDTO>          $toInsert
     * @param list<string>                    $toDelete
     */
    public function __construct(
        public array $toUpdate,
        public array $toInsert,
        public array $toDelete,
    ) {
    }
}
