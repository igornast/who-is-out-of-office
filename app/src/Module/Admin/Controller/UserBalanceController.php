<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\AdjustBalanceDTO;
use App\Module\Admin\Form\AdjustBalanceFormType;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\RoleEnum;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use App\Shared\Service\BalanceHistoryPaginationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserBalanceController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly BalanceHistoryPaginationService $pagination,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/app/my-organization/{id}/adjust-balance', name: 'app_user_adjust_balance', methods: ['GET', 'POST'])]
    public function __invoke(#[CurrentUser] User $currentUser, Request $request, string $id): Response
    {
        $targetUser = $this->userFacade->getUser($id);

        if (null === $targetUser) {
            throw new NotFoundHttpException(sprintf('User "%s" not found.', $id));
        }

        $adjustBalanceDTO = new AdjustBalanceDTO();
        $form = $this->createForm(AdjustBalanceFormType::class, $adjustBalanceDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
                userId: $targetUser->id,
                delta: $adjustBalanceDTO->delta,
                type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
                source: BalanceLedgerSourceEnum::App,
                reason: $adjustBalanceDTO->reason,
                actorUserId: $currentUser->id->toString(),
            ));

            $this->addFlash('success', 'user.adjust_balance.success');

            return $this->redirectToRoute('app_user_balance_history', ['id' => $targetUser->id]);
        }

        return $this->render('@AppAdmin/user/adjust_balance.html.twig', [
            'form' => $form->createView(),
            'target_user' => $targetUser,
        ]);
    }

    #[IsGranted(new Expression('"ROLE_ADMIN" in role_names or "ROLE_MANAGER" in role_names'))]
    #[Route('/app/my-organization/{id}/balance-history', name: 'app_user_balance_history', methods: ['GET'])]
    public function history(#[CurrentUser] User $currentUser, Request $request, string $id): Response
    {
        $targetUser = $this->userFacade->getUser($id);

        if (null === $targetUser) {
            throw new NotFoundHttpException(sprintf('User "%s" not found.', $id));
        }

        $this->denyAccessUnlessCanView($currentUser, $targetUser);

        $periods = $this->userFacade->getLeavePeriods($targetUser->id);

        if ([] === $periods) {
            return $this->render('@AppAdmin/user/balance_history.html.twig', [
                'target_user' => $targetUser,
                'periods' => [],
                'selected_period' => null,
                'statement' => null,
                'entries' => [],
            ]);
        }

        $selectedPeriod = $this->pagination->resolvePeriod($periods, $request->query->getString('period'));
        $page = $this->pagination->resolvePage($request->query->get('page', 1));
        if (null === $page) {
            return $this->redirectToRoute('app_user_balance_history', [
                'id' => $targetUser->id,
                'period' => $selectedPeriod->id,
            ]);
        }

        $entries = $this->userFacade->getBalanceLedger(
            $targetUser->id,
            $selectedPeriod->id,
            $this->pagination->pageSize() + 1,
            ($page - 1) * $this->pagination->pageSize(),
        );
        if ($page > 1 && [] === $entries) {
            return $this->redirectToRoute('app_user_balance_history', [
                'id' => $targetUser->id,
                'period' => $selectedPeriod->id,
            ]);
        }

        $hasNextPage = count($entries) > $this->pagination->pageSize();

        return $this->render('@AppAdmin/user/balance_history.html.twig', [
            'target_user' => $targetUser,
            'periods' => $periods,
            'selected_period' => $selectedPeriod,
            'statement' => $this->userFacade->getBalanceStatement($targetUser->id, $selectedPeriod->id),
            'entries' => array_slice($entries, 0, $this->pagination->pageSize()),
            'page' => $page,
            'has_previous_page' => $page > 1,
            'has_next_page' => $hasNextPage,
        ]);
    }

    private function denyAccessUnlessCanView(User $currentUser, UserDTO $targetUser): void
    {
        if ($this->isGranted(RoleEnum::Admin->value)) {
            return;
        }

        if ($targetUser->managerId === $currentUser->id->toString()) {
            return;
        }

        throw $this->createAccessDeniedException();
    }
}
