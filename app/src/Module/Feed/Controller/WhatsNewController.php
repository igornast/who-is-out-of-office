<?php

declare(strict_types=1);

namespace App\Module\Feed\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Facade\FeedFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WhatsNewController extends AbstractController
{
    public function __construct(
        private readonly FeedFacadeInterface $feedFacade,
    ) {
    }

    #[Route('/app/whats-new', name: 'app_whats_new', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $previousSeenAt = $user->feedLastSeenAt ?? $user->getCreatedAt();
        $grouped = $this->feedFacade->getRecentItemsGrouped(50);

        return $this->render('@AppFeed/whats_new.html.twig', [
            'grouped' => $grouped,
            'previous_seen_at' => $previousSeenAt,
        ]);
    }

    #[Route('/app/whats-new/mark-as-read', name: 'app_whats_new_mark_read', methods: ['POST'])]
    public function markAsRead(#[CurrentUser] User $user, Request $request): Response
    {
        $token = $request->request->getString('_csrf_token', '');
        if (!$this->isCsrfTokenValid('mark_feed_as_read', $token)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $this->feedFacade->markAsReadForUser($user->id->toString());

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
