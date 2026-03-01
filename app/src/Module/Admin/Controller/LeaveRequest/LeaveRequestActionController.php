<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\NewLeaveRequestDTO;
use App\Module\Admin\Form\NewLeaveRequestFormType;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\RoleEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class LeaveRequestActionController extends AbstractController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UserFacadeInterface $userFacade,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    #[Route('/app/leave-request/{id}/withdraw', name: 'app_leave_request_withdraw', methods: ['POST'])]
    public function withdraw(Request $request, LeaveRequest $leaveRequest): Response
    {
        if (!$this->isCsrfTokenValid(sprintf('withdraw%s', $leaveRequest->id), $this->extractToken($request))) {
            if ($this->wantsJson($request)) {
                return $this->json(['success' => false, 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
            }

            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser->id->toString() !== $leaveRequest->user->id->toString()) {
            if ($this->wantsJson($request)) {
                return $this->json(['success' => false, 'message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
            }

            throw $this->createAccessDeniedException();
        }

        if (!in_array($leaveRequest->status, [LeaveRequestStatusEnum::Pending, LeaveRequestStatusEnum::Approved], true)) {
            if ($this->wantsJson($request)) {
                return $this->json(['success' => false, 'message' => 'Only pending or approved requests can be withdrawn.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('warning', 'Only pending or approved requests can be withdrawn.');

            return $this->redirectToDetail($leaveRequest);
        }

        $dto = LeaveRequestDTO::fromEntity($leaveRequest);
        $dto->status = LeaveRequestStatusEnum::Withdrawn;
        $this->leaveRequestFacade->update($dto);

        $this->restoreLeaveBalanceIfNeeded($leaveRequest);

        if ($this->wantsJson($request)) {
            return $this->json([
                'success' => true,
                'message' => 'Leave request withdrawn.',
                'status' => LeaveRequestStatusEnum::Withdrawn->value,
                'id' => $leaveRequest->id->toString(),
            ]);
        }

        $this->addFlash('success', 'Leave request withdrawn.');

        return $this->redirectToDetail($leaveRequest);
    }

    #[Route('/app/leave-request/{id}/approve', name: 'app_leave_request_approve', methods: ['POST'])]
    public function approve(Request $request, LeaveRequest $leaveRequest): Response
    {
        if (!$this->isCsrfTokenValid(sprintf('approve%s', $leaveRequest->id), $this->extractToken($request))) {
            if ($this->wantsJson($request)) {
                return $this->json(['success' => false, 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
            }

            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->denyUnlessCanManageRequest($leaveRequest);
        } catch (AccessDeniedException $e) {
            if ($this->wantsJson($request)) {
                $message = $e->getMessage() ?: 'Access denied.';

                return $this->json(['success' => false, 'message' => $message], Response::HTTP_FORBIDDEN);
            }

            throw $e;
        }

        if (LeaveRequestStatusEnum::Pending !== $leaveRequest->status) {
            if ($this->wantsJson($request)) {
                return $this->json(['success' => false, 'message' => 'Only pending requests can be approved.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('warning', 'Only pending requests can be approved.');

            return $this->redirectToDetail($leaveRequest);
        }

        /** @var User $approver */
        $approver = $this->getUser();

        $dto = LeaveRequestDTO::fromEntity($leaveRequest);
        $dto->status = LeaveRequestStatusEnum::Approved;
        $dto->approvedBy = UserDTO::fromEntity($approver);
        $this->leaveRequestFacade->update($dto);

        if ($this->wantsJson($request)) {
            return $this->json([
                'success' => true,
                'message' => 'Leave request approved.',
                'status' => LeaveRequestStatusEnum::Approved->value,
                'id' => $leaveRequest->id->toString(),
            ]);
        }

        $this->addFlash('success', 'Leave request approved.');

        return $this->redirectToDetail($leaveRequest);
    }

    #[Route('/app/leave-request/{id}/reject', name: 'app_leave_request_reject', methods: ['POST'])]
    public function reject(Request $request, LeaveRequest $leaveRequest): Response
    {
        if (!$this->isCsrfTokenValid(sprintf('reject%s', $leaveRequest->id), $this->extractToken($request))) {
            if ($this->wantsJson($request)) {
                return $this->json(['success' => false, 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
            }

            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->denyUnlessCanManageRequest($leaveRequest);
        } catch (AccessDeniedException $e) {
            if ($this->wantsJson($request)) {
                $message = $e->getMessage() ?: 'Access denied.';

                return $this->json(['success' => false, 'message' => $message], Response::HTTP_FORBIDDEN);
            }

            throw $e;
        }

        if (LeaveRequestStatusEnum::Pending !== $leaveRequest->status) {
            if ($this->wantsJson($request)) {
                return $this->json(['success' => false, 'message' => 'Only pending requests can be rejected.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('warning', 'Only pending requests can be rejected.');

            return $this->redirectToDetail($leaveRequest);
        }

        /** @var User $approver */
        $approver = $this->getUser();

        $dto = LeaveRequestDTO::fromEntity($leaveRequest);
        $dto->status = LeaveRequestStatusEnum::Rejected;
        $dto->approvedBy = UserDTO::fromEntity($approver);
        $this->leaveRequestFacade->update($dto);

        $this->restoreLeaveBalanceIfNeeded($leaveRequest);

        if ($this->wantsJson($request)) {
            return $this->json([
                'success' => true,
                'message' => 'Leave request rejected.',
                'status' => LeaveRequestStatusEnum::Rejected->value,
                'id' => $leaveRequest->id->toString(),
            ]);
        }

        $this->addFlash('success', 'Leave request rejected.');

        return $this->redirectToDetail($leaveRequest);
    }

    private function denyUnlessCanManageRequest(LeaveRequest $leaveRequest): void
    {
        if (!$this->isGranted(RoleEnum::Admin->value) && !$this->isGranted(RoleEnum::Manager->value)) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser->id->toString() === $leaveRequest->user->id->toString()) {
            throw $this->createAccessDeniedException('You cannot approve or reject your own request.');
        }

        if ($this->isGranted(RoleEnum::Manager->value) && !$this->isGranted(RoleEnum::Admin->value)) {
            $managerId = $leaveRequest->user->manager?->id->toString();
            if ($managerId !== $currentUser->id->toString()) {
                throw $this->createAccessDeniedException();
            }
        }
    }

    private function restoreLeaveBalanceIfNeeded(LeaveRequest $leaveRequest): void
    {
        if (true === $leaveRequest->leaveType->isAffectingBalance) {
            $this->userFacade->updateUserCurrentLeaveBalance($leaveRequest->user->id->toString(), $leaveRequest->workDays);
        }
    }

    private function extractToken(Request $request): ?string
    {
        $token = $request->request->get('_token') ?? $request->query->get('_token');

        return is_string($token) ? $token : null;
    }

    private function wantsJson(Request $request): bool
    {
        return 'application/json' === $request->headers->get('Accept');
    }

    private function redirectToDetail(LeaveRequest $leaveRequest): RedirectResponse
    {
        $url = $this->adminUrlGenerator
            ->setController(LeaveRequestCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($leaveRequest->id)
            ->generateUrl();

        return $this->redirect($url);
    }

    #[AdminRoute('/leave-requests/new', name: 'requests_new')] // app_dashboard_requests_new
    public function new(Request $request): Response
    {
        $dto = new NewLeaveRequestDTO();
        $form = $this->createForm(NewLeaveRequestFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $this->leaveRequestFacade->save(new SaveLeaveRequestCommand(
                LeaveRequestTypeDTO::fromEntity($dto->leaveType),
                $dto->startDate,
                $dto->endDate,
                UserDTO::fromEntity($user),
            ));

            $this->addFlash('success', 'The leave request has been created.');

            $url = $this->adminUrlGenerator
                ->setController(LeaveRequestCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl();

            return $this->redirect($url);
        }

        return $this->render('@AppAdmin/leave_request/new.html.twig');
    }
}
