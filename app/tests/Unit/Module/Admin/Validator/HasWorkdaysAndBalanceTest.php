<?php

declare(strict_types=1);

use App\Module\Admin\Validator\HasWorkdaysAndBalance;
use App\Module\Admin\Validator\HasWorkdaysAndBalanceValidator;

it('is validated by HasWorkdaysAndBalanceValidator', function (): void {
    expect(new HasWorkdaysAndBalance()->validatedBy())->toBe(HasWorkdaysAndBalanceValidator::class);
});

it('carries a default message for every rule it enforces', function (): void {
    $constraint = new HasWorkdaysAndBalance();

    expect($constraint->noWorkdaysMessage)->toBe('The selected range does not include any workdays.')
        ->and($constraint->notEnoughBalanceMessage)->toBe('Your leave balance is insufficient for this request.')
        ->and($constraint->minNoticeDaysMessage)->toBe('Leave requests must be submitted at least {{ days }} days in advance.')
        ->and($constraint->maxConsecutiveDaysMessage)->toBe('Leave requests cannot exceed {{ days }} consecutive workdays.');
});
