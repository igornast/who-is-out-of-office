<?php

declare(strict_types=1);

namespace App\Module\Admin\Validator;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Shared\Facade\LeaveRequestFacadeInterface;
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

    public function validate(mixed $value, Constraint $constraint)
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

        /** @var LeaveRequest $formData */
        $formData =  $this->context->getObject()?->getParent()?->getData();

        $startDate = $formData->startDate;

        if (!$startDate instanceof \DateTimeInterface) {
            throw new UnexpectedValueException(null, 'startDate');
        }

        $workdays = $this->leaveRequestFacade->calculateWorkDays($startDate, $value);

        if ($workdays < 1) {
            $this->context
                ->buildViolation($constraint->noWorkdaysMessage)
                ->addViolation();

            return;
        }

        if ($formData->user->currentLeaveBalance < $workdays) {
            $this->context
                ->buildViolation($constraint->notEnoughBalanceMessage)
                ->addViolation();
        }
    }
}
