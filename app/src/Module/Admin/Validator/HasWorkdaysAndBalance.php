<?php

declare(strict_types=1);

namespace App\Module\Admin\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class HasWorkdaysAndBalance extends Constraint
{
    public string $noWorkdaysMessage = 'The selected range does not include any workdays.';

    public string $notEnoughBalanceMessage = 'Your leave balance is insufficient for this request.';

    public string $minNoticeDaysMessage = 'Leave requests must be submitted at least {{ days }} days in advance.';

    public string $maxConsecutiveDaysMessage = 'Leave requests cannot exceed {{ days }} consecutive workdays.';

    public function validatedBy(): string
    {
        return HasWorkdaysAndBalanceValidator::class;
    }
}
