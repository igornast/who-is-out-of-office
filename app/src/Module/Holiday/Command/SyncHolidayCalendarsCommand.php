<?php

declare(strict_types=1);

namespace App\Module\Holiday\Command;

use App\Shared\Facade\HolidayFacadeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[
    AsCommand(name: 'app:holiday:sync', description: 'Sync all active holiday calendars for the current year'),
    AsCronTask(expression: '0 2 1 1 *'),
]
class SyncHolidayCalendarsCommand
{
    public function __construct(private readonly HolidayFacadeInterface $holidayFacade)
    {
    }

    public function __invoke(): int
    {
        $currentYear = (int) (new \DateTimeImmutable())->format('Y');

        $this->holidayFacade->syncAllActiveCalendars($currentYear);

        return Command::SUCCESS;
    }
}
