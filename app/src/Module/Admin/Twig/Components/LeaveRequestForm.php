<?php

declare(strict_types=1);

namespace App\Module\Admin\Twig\Components;

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Form\NewLeaveRequestFormType;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Handler\LeaveRequest\Query\CalculateWorkdaysQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('LeaveRequestForm', template: '@AppAdmin/component/LeaveRequestForm.html.twig')]
class LeaveRequestForm extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    public string $infoBox = '';

    public bool $isSubmitDisabled = true;

    #[LiveProp(writable: true)]
    public ?LeaveRequestType $leaveType = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(NewLeaveRequestFormType::class, null);
    }

    #[LiveAction]
    public function updated(
        #[CurrentUser]
        User $user,
        LeaveRequestFacadeInterface $leaveRequestFacade,
        AppSettingsFacadeInterface $appSettingsFacade,
    ): void {
        $this->isSubmitDisabled = true;
        $this->infoBox = '';

        $rangeString = $this->formValues['dateRange'] ?? null;
        if (empty($rangeString)) {
            return;
        }

        [$start, $end] = $this->extractStartAndEnd($rangeString);
        if (empty($start) || null === $this->leaveType) {
            return;
        }

        if (false === $this->leaveType->isAffectingBalance) {
            $this->isSubmitDisabled = false;

            return;
        }

        if (empty($end)) {
            $end = $start;
        }

        $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', $end);

        if (!$startDate || !$endDate) {
            throw new \InvalidArgumentException('Start date and end date are not valid');
        }

        $minNoticeDays = $appSettingsFacade->minNoticeDays();
        if ($minNoticeDays > 0) {
            $today = new \DateTimeImmutable('today');
            $earliestAllowed = $today->modify(sprintf('+%d days', $minNoticeDays));

            if ($startDate < $earliestAllowed) {
                $this->infoBox = $this->generateMinNoticeBox($minNoticeDays);

                return;
            }
        }

        $query = new CalculateWorkdaysQuery(
            startDate: $startDate,
            endDate: $endDate,
            userWorkingDays: $user->workingDays,
            holidayCalendarCountryCode: $user->holidayCalendar?->countryCode,
            subdivisionCode: $user->subdivisionCode,
        );

        $workdaysNumber = $leaveRequestFacade->calculateWorkDays($query);

        $maxConsecutiveDays = $appSettingsFacade->maxConsecutiveDays();
        if ($maxConsecutiveDays > 0 && $workdaysNumber > $maxConsecutiveDays) {
            $this->infoBox = $this->generateMaxConsecutiveDaysBox($maxConsecutiveDays);

            return;
        }

        $remainingBalance = $user->currentLeaveBalance - $workdaysNumber;

        if ($remainingBalance < 0) {
            $this->infoBox = $this->generateNoBalanceBox($user->currentLeaveBalance);

            return;
        }

        $this->infoBox = $this->generateInfoBox($workdaysNumber, $remainingBalance);
        $this->isSubmitDisabled = false;
    }

    /**
     * @return string[]
     */
    public function extractStartAndEnd(string $rageString): array
    {
        return explode(' to ', $rageString.' to ');
    }

    private function generateInfoBox(int $workdaysNumber, int $remainingBalance): string
    {
        $message = $this->translator->trans(
            'leave_request.new.info_box',
            ['%workdays%' => $workdaysNumber, '%remaining%' => $remainingBalance],
            'admin',
        );

        return sprintf('<div class="alert alert-info mt-3" id="infoBox">%s</div>', $message);
    }

    private function generateMinNoticeBox(int $minNoticeDays): string
    {
        $message = $this->translator->trans(
            'leave_request.new.min_notice_box',
            ['%days%' => $minNoticeDays],
            'admin',
        );

        return sprintf('<div class="alert alert-warning mt-3" id="minNoticeBox">%s</div>', $message);
    }

    private function generateMaxConsecutiveDaysBox(int $maxConsecutiveDays): string
    {
        $message = $this->translator->trans(
            'leave_request.new.max_consecutive_box',
            ['%days%' => $maxConsecutiveDays],
            'admin',
        );

        return sprintf('<div class="alert alert-warning mt-3" id="maxConsecutiveBox">%s</div>', $message);
    }

    private function generateNoBalanceBox(int $remainingBalance): string
    {
        $message = $this->translator->trans(
            'leave_request.new.no_balance_box',
            ['%remaining%' => $remainingBalance],
            'admin',
        );

        return sprintf('<div class="alert alert-warning mt-3" id="noBalanceBox">%s</div>', $message);
    }
}
