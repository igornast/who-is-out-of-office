<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\NewLeaveRequestDTO;
use App\Module\Admin\Form\NewLeaveRequestFormType;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestPermission;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\EmailFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
class LeaveRequestActionController extends AbstractController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly TranslatorInterface $translator,
        private readonly EmailFacadeInterface $emailFacade,
    ) {
    }

    #[Route('/app/leave-request/{id}/withdraw', name: 'app_leave_request_withdraw', methods: ['POST'])]
    public function withdraw(Request $request, string $id): JsonResponse
    {
        return $this->handleAction($request, $id, 'withdraw', function (LeaveRequestDTO $dto): void {
            $this->denyAccessUnlessGranted(LeaveRequestPermission::Withdraw->value, $dto);

            if (!in_array($dto->status, [LeaveRequestStatusEnum::Pending, LeaveRequestStatusEnum::Approved], true)) {
                throw new \DomainException('Only pending or approved requests can be withdrawn.');
            }

            $dto->status = LeaveRequestStatusEnum::Withdrawn;
            $this->leaveRequestFacade->updateAndRestoreBalanceIfNeeded($dto);
            $this->emailFacade->sendLeaveRequestWithdrawnEmail($dto);
        });
    }

    #[Route('/app/leave-request/{id}/approve', name: 'app_leave_request_approve', methods: ['POST'])]
    public function approve(Request $request, string $id): JsonResponse
    {
        return $this->handleAction($request, $id, 'approve', function (LeaveRequestDTO $dto): void {
            $this->denyAccessUnlessGranted(LeaveRequestPermission::Manage->value, $dto);

            if (LeaveRequestStatusEnum::Pending !== $dto->status) {
                throw new \DomainException('Only pending requests can be approved.');
            }

            $dto->status = LeaveRequestStatusEnum::Approved;
            $dto->approvedBy = UserDTO::fromEntity($this->getCurrentUser());
            $this->leaveRequestFacade->update($dto);
            $this->emailFacade->sendLeaveRequestApprovedEmail($dto);
        });
    }

    #[Route('/app/leave-request/{id}/reject', name: 'app_leave_request_reject', methods: ['POST'])]
    public function reject(Request $request, string $id): JsonResponse
    {
        return $this->handleAction($request, $id, 'reject', function (LeaveRequestDTO $dto): void {
            $this->denyAccessUnlessGranted(LeaveRequestPermission::Manage->value, $dto);

            if (LeaveRequestStatusEnum::Pending !== $dto->status) {
                throw new \DomainException('Only pending requests can be rejected.');
            }

            $dto->status = LeaveRequestStatusEnum::Rejected;
            $dto->approvedBy = UserDTO::fromEntity($this->getCurrentUser());
            $this->leaveRequestFacade->updateAndRestoreBalanceIfNeeded($dto);
            $this->emailFacade->sendLeaveRequestRejectedEmail($dto);
        });
    }

    /**
     * @param \Closure(LeaveRequestDTO): void $action
     */
    private function handleAction(Request $request, string $id, string $actionName, \Closure $action): JsonResponse
    {
        $dto = $this->getLeaveRequestOrFail($id);

        $token = $request->request->get('_token') ?? $request->query->get('_token');
        if (!$this->isCsrfTokenValid(sprintf('%s%s', $actionName, $dto->id), is_string($token) ? $token : null)) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('leave_request.action.error.invalid_csrf', domain: 'admin'),
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $action($dto);
        } catch (\DomainException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (AccessDeniedException) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('leave_request.action.error.access_denied', domain: 'admin'),
            ], Response::HTTP_FORBIDDEN);
        }

        $statusKey = match ($actionName) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'withdraw' => 'withdrawn',
            default => $actionName.'ed',
        };

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans(sprintf('leave_request.action.success.%s', $statusKey), domain: 'admin'),
            'status' => $dto->status->value,
            'id' => $dto->id->toString(),
        ]);
    }

    private function getLeaveRequestOrFail(string $id): LeaveRequestDTO
    {
        return $this->leaveRequestFacade->getById($id)
            ?? throw new NotFoundHttpException(sprintf('Leave request "%s" not found.', $id));
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    #[AdminRoute('/leave-requests/new', name: 'requests_new')] // app_dashboard_requests_new
    public function new(Request $request): Response
    {
        $dto = new NewLeaveRequestDTO();
        $form = $this->createForm(NewLeaveRequestFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->leaveRequestFacade->save(new SaveLeaveRequestCommand(
                LeaveRequestTypeDTO::fromEntity($dto->leaveType),
                $dto->startDate,
                $dto->endDate,
                UserDTO::fromEntity($this->getCurrentUser()),
            ));

            $this->addFlash('success', 'The leave request has been created.');

            return $this->redirect($this->urlGenerator->generate('app_dashboard_app_leave_request_index'));
        }

        return $this->render('@AppAdmin/leave_request/new.html.twig');
    }
}
