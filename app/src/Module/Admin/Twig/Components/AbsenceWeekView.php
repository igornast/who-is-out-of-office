<?php

declare(strict_types=1);

namespace App\Module\Admin\Twig\Components;

use App\Shared\DTO\Dashboard\DailyAbsenceSummaryDTO;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('AbsenceWeekView', template: '@AppAdmin/component/AbsenceWeekView.html.twig')]
class AbsenceWeekView
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $weekStart = null;

    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    #[LiveAction]
    public function prev(): void
    {
        $this->weekStart = $this->getWeekStartDate()->modify('-7 days')->format('Y-m-d');
    }

    #[LiveAction]
    public function next(): void
    {
        $this->weekStart = $this->getWeekStartDate()->modify('+7 days')->format('Y-m-d');
    }

    /**
     * @return DailyAbsenceSummaryDTO[]
     */
    public function getDays(): array
    {
        return $this->leaveRequestFacade->getDailyAbsenceSummary($this->getWeekStartDate());
    }

    public function isCurrentWeek(): bool
    {
        return $this->getWeekStartDate()->format('Y-m-d') === new \DateTimeImmutable('monday this week')->format('Y-m-d');
    }

    public function getFormattedDateRange(): string
    {
        $monday = $this->getWeekStartDate();
        $friday = $monday->modify('+4 days');

        if ($monday->format('M') === $friday->format('M')) {
            return sprintf('%s %s–%s, %s', $monday->format('M'), $monday->format('j'), $friday->format('j'), $friday->format('Y'));
        }

        return sprintf('%s %s – %s %s, %s', $monday->format('M'), $monday->format('j'), $friday->format('M'), $friday->format('j'), $friday->format('Y'));
    }

    private function getWeekStartDate(): \DateTimeImmutable
    {
        return null !== $this->weekStart
            ? new \DateTimeImmutable($this->weekStart)
            : new \DateTimeImmutable('monday this week');
    }
}
