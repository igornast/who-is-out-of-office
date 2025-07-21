<?php

declare(strict_types=1);

namespace App\Module\Admin\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class HasWorkdaysAndBalance extends Constraint
{
    public string $noWorkdaysMessage = 'The selected range does not include any workdays.';

    public string $notEnoughBalanceMessage = 'Your leave balance is insufficient for this request.';

    public function validatedBy(): string
    {
        return HasWorkdaysAndBalanceValidator::class;
    }
}
