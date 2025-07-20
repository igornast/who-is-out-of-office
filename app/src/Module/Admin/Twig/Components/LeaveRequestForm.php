<?php

declare(strict_types=1);

namespace App\Module\Admin\Twig\Components;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\LeaveRequestDraftDTO;
use App\Module\Admin\Form\LeaveRequestDraftType;
use App\Shared\DTO\LeaveRequest\Query\CalculateWorkdaysQuery;
use App\Shared\Enum\LeaveRequestTypeEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('LeaveRequestForm', template: '@AppAdmin/component/LeaveRequestForm.html.twig')]
class LeaveRequestForm extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;
    use ComponentToolsTrait;

    public string $infoBox = '';

    public bool $isSubmitDisabled = true;

    #[LiveProp]
    public ?LeaveRequestDraftDTO $dto = null;

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(LeaveRequestDraftType::class, $this->dto);
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
        $leaveType = $this->formValues['leaveType'] ?? null;

        if (empty($start) || empty($leaveType)) {
            return;
        }

        if ($leaveType === LeaveRequestTypeEnum::SickLeave->value) {
            $this->isSubmitDisabled = false;

            return;
        }

        if (empty($end)) {
            $end = $start;
        }

        $query = new CalculateWorkdaysQuery(
            startDate: \DateTimeImmutable::createFromFormat('Y-m-d', $start),
            endDate: \DateTimeImmutable::createFromFormat('Y-m-d', $end),
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
        <div class="alert alert-info mt-3">
            <strong>{$workdaysNumber}</strong> workdays will be taken.
            Remaining balance: <strong>{$remainingBalance}</strong>.
        </div>
HTML;

    }

    private function generateNoBalanceBox(int $remainingBalance): string
    {
        return <<<HTML
        <div class="alert alert-warning mt-3">
            Oops! It looks like you don’t have enough leave days to cover this request.</br>
            You currently have <strong>{$remainingBalance}</strong> days of leave balance remaining.
        </div>
HTML;
    }
}
