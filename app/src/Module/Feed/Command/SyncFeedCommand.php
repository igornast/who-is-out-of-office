<?php

declare(strict_types=1);

namespace App\Module\Feed\Command;

use App\Shared\Facade\FeedFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[
    AsCommand(name: 'app:feed:sync', description: 'Sync the in-app feed from whoisooo.app'),
    AsCronTask(expression: '0 */6 * * *'),
]
class SyncFeedCommand
{
    public function __construct(
        private readonly FeedFacadeInterface $feedFacade,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): int
    {
        try {
            $this->feedFacade->sync();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('[FEED][SYNC]: Unexpected error during feed sync.', [
                'exception' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
