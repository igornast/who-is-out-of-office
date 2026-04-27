<?php

declare(strict_types=1);

namespace App\Module\Feed\Twig;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Facade\FeedFacadeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FeedExtension extends AbstractExtension
{
    private ?int $cachedCount = null;

    public function __construct(
        private readonly FeedFacadeInterface $feedFacade,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('feed_unread_count', $this->getUnreadCount(...)),
        ];
    }

    public function getUnreadCount(): int
    {
        if (null !== $this->cachedCount) {
            return $this->cachedCount;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->cachedCount = 0;
        }

        return $this->cachedCount = $this->feedFacade->getUnreadCountForUser($user->id->toString());
    }
}
