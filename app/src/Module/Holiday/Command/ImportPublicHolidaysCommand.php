<?php

declare(strict_types=1);

namespace App\Module\Holiday\Command;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\Facade\DateNagerInterface;
use App\Shared\Facade\HolidayFacadeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:holiday:import',
    description: 'Imports public holidays from Nager.Date and saves to DB'
)]
class ImportPublicHolidaysCommand extends Command
{
    public function __construct(
        private readonly DateNagerInterface $dateNagerFacade,
        private readonly HolidayFacadeInterface $holidayFacade,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('country-code', InputArgument::REQUIRED, 'Country code (e.g. DE, SE, PL)');
        $this->addArgument('country-name', InputArgument::REQUIRED, 'Country name (e.g. Germany, Sweden, Poland)');
        $this->addArgument('year', InputArgument::REQUIRED, 'Year (e.g. 2025)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $countryCode = strtoupper($input->getArgument('country-code'));
        $countryName = ucfirst($input->getArgument('country-name'));
        $year = (int) $input->getArgument('year');

        $holidays = $this->dateNagerFacade->getHolidaysForCountry($countryCode, $year);

        $holidayCalendarDTO = PublicHolidayCalendarDTO::createFromNager($countryCode, $countryName, $holidays);

        $this->holidayFacade->upsertHolidayCalendar($holidayCalendarDTO);

        return Command::SUCCESS;
    }
}
