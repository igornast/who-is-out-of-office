<?php

declare(strict_types=1);

namespace App\Module\Admin\Twig\Components;

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Form\NewLeaveRequestFormType;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Handler\LeaveRequest\Query\CalculateWorkdaysQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
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

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(NewLeaveRequestFormType::class, null);
    }

    #[LiveAction]
    public function updated(
        #[CurrentUser]
        User $user,
        LeaveRequestFacadeInterface $leaveRequestFacade,
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

        $query = new CalculateWorkdaysQuery(
            startDate: $startDate,
            endDate: $endDate,
            userWorkingDays: $user->workingDays,
            holidayCalendarCountryCode: $user->holidayCalendar?->countryCode
        );

        $workdaysNumber = $leaveRequestFacade->calculateWorkDays($query);
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
        return <<<HTML
        <div class="alert alert-info mt-3" id="infoBox">
            <strong>{$workdaysNumber}</strong> workdays will be taken.
            Remaining balance: <strong>{$remainingBalance}</strong>.
        </div>
HTML;

    }

    private function generateNoBalanceBox(int $remainingBalance): string
    {
        return <<<HTML
        <div class="alert alert-warning mt-3" id="noBalanceBox">
            Oops! It looks like you don’t have enough leave days to cover this request.</br>
            You currently have <strong>{$remainingBalance}</strong> days of leave balance remaining.
        </div>
HTML;
    }
}
