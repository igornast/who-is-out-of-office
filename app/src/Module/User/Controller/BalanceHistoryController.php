<?php

declare(strict_types=1);

namespace App\Module\User\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Service\BalanceHistoryPaginationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BalanceHistoryController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly BalanceHistoryPaginationService $pagination,
    ) {
    }

    #[Route('/app/balance-history', name: 'app_balance_history', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $user, Request $request): Response
    {
        $userId = $user->id->toString();
        $periods = $this->userFacade->getLeavePeriods($userId);

        if ([] === $periods) {
            return $this->render('@AppUser/balance_history.html.twig', [
                'periods' => [],
                'selected_period' => null,
                'statement' => null,
                'entries' => [],
            ]);
        }

        $selectedPeriod = $this->pagination->resolvePeriod($periods, $request->query->getString('period'));
        $page = $this->pagination->resolvePage($request->query->get('page', 1));
        if (null === $page) {
            return $this->redirectToRoute('app_balance_history', ['period' => $selectedPeriod->id]);
        }

        $entries = $this->userFacade->getBalanceLedger(
            $userId,
            $selectedPeriod->id,
            $this->pagination->pageSize() + 1,
            ($page - 1) * $this->pagination->pageSize(),
        );
        if ($page > 1 && [] === $entries) {
            return $this->redirectToRoute('app_balance_history', ['period' => $selectedPeriod->id]);
        }

        $hasNextPage = count($entries) > $this->pagination->pageSize();

        return $this->render('@AppUser/balance_history.html.twig', [
            'periods' => $periods,
            'selected_period' => $selectedPeriod,
            'statement' => $this->userFacade->getBalanceStatement($userId, $selectedPeriod->id),
            'entries' => array_slice($entries, 0, $this->pagination->pageSize()),
            'page' => $page,
            'has_previous_page' => $page > 1,
            'has_next_page' => $hasNextPage,
        ]);
    }
}
