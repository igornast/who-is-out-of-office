<?php

declare(strict_types=1);

namespace App\Module\Admin\Validator;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\DTO\LeaveRequest\Query\CalculateWorkdaysQuery;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class HasWorkdaysAndBalanceValidator extends ConstraintValidator
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof HasWorkdaysAndBalance) {
            throw new UnexpectedTypeException($constraint, HasWorkdaysAndBalance::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof \DateTimeImmutable) {
            throw new UnexpectedValueException($value, 'datetime');
        }

        /** @var Form $form */
        $form = $this->context->getObject();

        /** @var LeaveRequest $leaveRequest */
        $leaveRequest =  $form->getParent()?->getData();

        $startDate = $leaveRequest->startDate;
        $user = $leaveRequest->user;

        $query = new CalculateWorkdaysQuery(
            startDate: $startDate,
            endDate: $value,
            userWorkingDays: $user->workingDays,
            holidayCalendarCountryCode: $user->holidayCalendar?->countryCode
        );

        $workdays = $this->leaveRequestFacade->calculateWorkDays($query);

        if ($workdays < 1) {
            $this->context
                ->buildViolation($constraint->noWorkdaysMessage)
                ->addViolation();

            return;
        }

        /** @var User $user */
        if ($user->currentLeaveBalance < $workdays) {
            $this->context
                ->buildViolation($constraint->notEnoughBalanceMessage)
                ->addViolation();
        }
    }
}
