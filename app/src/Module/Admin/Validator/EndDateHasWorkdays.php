<?php

declare(strict_types=1);

namespace App\Module\Admin\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class EndDateHasWorkdays extends Constraint
{
    public string $message = 'The selected end date has no workdays.';

    public function validatedBy(): string
    {
        return EndDateHasWorkdaysValidator::class;
    }
}
