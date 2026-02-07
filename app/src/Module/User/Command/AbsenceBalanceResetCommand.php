<?php

declare(strict_types=1);

namespace App\Module\User\Command;

use App\Shared\Facade\UserFacadeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[
    AsCommand(name: 'user:absence-balance-reset', description: 'Reset absence balance for users'),
    AsCronTask(expression: '0 0 * * *'),
]
class AbsenceBalanceResetCommand
{
    public function __construct(private readonly UserFacadeInterface $userFacade)
    {
    }

    public function __invoke(): int
    {
        $this->userFacade->resetAbsenceBalance();

        return Command::SUCCESS;
    }
}
