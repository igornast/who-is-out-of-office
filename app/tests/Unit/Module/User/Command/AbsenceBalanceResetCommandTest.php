<?php

declare(strict_types=1);

use App\Module\User\Command\AbsenceBalanceResetCommand;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Component\Console\Command\Command;

beforeEach(function (): void {
    $this->userFacade = mock(UserFacadeInterface::class);

    $this->command = new AbsenceBalanceResetCommand(userFacade: $this->userFacade);
});

it('calls reset absence balance and returns success', function () {
    $this->userFacade
        ->expects('resetAbsenceBalance')
        ->once();

    $result = ($this->command)();

    expect($result)->toBe(Command::SUCCESS);
});
