<?php

declare(strict_types=1);

namespace App\Module\Admin\Validator;

use App\Shared\Facade\LeaveRequestFacadeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class EndDateHasWorkdaysValidator extends ConstraintValidator
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof EndDateHasWorkdays) {
            throw new UnexpectedTypeException($constraint, EndDateHasWorkdays::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof \DateTimeImmutable) {
            throw new UnexpectedValueException($value, 'datetime');
        }

        $startDate = $this->context->getObject()?->getParent()?->getData()?->startDate;

        if (!$startDate instanceof \DateTimeInterface) {
            throw new UnexpectedValueException(null, 'startDate');
        }

        $workdays = $this->leaveRequestFacade->calculateWorkDays($startDate, $value);

        if ($workdays > 0) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->addViolation();
    }
}
