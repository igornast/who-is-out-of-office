<?php

declare(strict_types=1);

namespace App\Module\Admin\Validator;

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\NewLeaveRequestDTO;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Handler\LeaveRequest\Query\CalculateWorkdaysQuery;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class HasWorkdaysAndBalanceValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
    ) {
    }

    /**
     * @param mixed|array{'start': \DateTimeImmutable, 'end': \DateTimeImmutable} $value
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof HasWorkdaysAndBalance) {
            throw new UnexpectedTypeException($constraint, HasWorkdaysAndBalance::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_array($value) || !isset($value['start']) || !isset($value['end'])) {
            throw new UnexpectedValueException($value, 'datetime');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        /** @var Form $form */
        $form = $this->context->getObject();

        /** @var NewLeaveRequestDTO $newLeaveRequestDTO */
        $newLeaveRequestDTO = $form->getParent()?->getData();

        $leaveType = $newLeaveRequestDTO->leaveType;
        if (!$leaveType instanceof LeaveRequestType) {
            throw new UnexpectedTypeException($leaveType, LeaveRequestType::class);
        }

        if (false === $leaveType->isAffectingBalance) {
            return;
        }

        $minNoticeDays = $this->appSettingsFacade->minNoticeDays();
        if ($minNoticeDays > 0) {
            $today = new \DateTimeImmutable('today');
            $earliestAllowed = $today->modify(sprintf('+%d days', $minNoticeDays));

            if ($value['start'] < $earliestAllowed) {
                $this->context
                    ->buildViolation($constraint->minNoticeDaysMessage)
                    ->setParameter('{{ days }}', (string) $minNoticeDays)
                    ->addViolation();

                return;
            }
        }

        $query = new CalculateWorkdaysQuery(
            startDate: $value['start'],
            endDate: $value['end'],
            userWorkingDays: $user->workingDays,
            holidayCalendarCountryCode: $user->holidayCalendar?->countryCode,
            subdivisionCode: $user->subdivisionCode,
        );

        $workdays = $this->leaveRequestFacade->calculateWorkDays($query);

        $maxConsecutiveDays = $this->appSettingsFacade->maxConsecutiveDays();
        if ($maxConsecutiveDays > 0 && $workdays > $maxConsecutiveDays) {
            $this->context
                ->buildViolation($constraint->maxConsecutiveDaysMessage)
                ->setParameter('{{ days }}', (string) $maxConsecutiveDays)
                ->addViolation();

            return;
        }

        if ($workdays < 1) {
            $this->context
                ->buildViolation($constraint->noWorkdaysMessage)
                ->addViolation();

            return;
        }

        if ($user->currentLeaveBalance < $workdays) {
            $this->context
                ->buildViolation($constraint->notEnoughBalanceMessage)
                ->addViolation();
        }
    }
}
