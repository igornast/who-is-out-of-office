<?php

declare(strict_types=1);

namespace App\Module\Admin\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class HasWorkdaysAndBalance extends Constraint
{
    public string $noWorkdaysMessage = 'The selected end date has no workdays.';

    public string $notEnoughBalanceMessage = 'You don\'t have enough available days balance.';

    public function validatedBy(): string
    {
        return HasWorkdaysAndBalanceValidator::class;
    }
}
