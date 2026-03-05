<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\NewLeaveRequestDTO;
use App\Module\Admin\Form\NewLeaveRequestFormType;
use App\Shared\Enum\LeaveRequestPermission;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class LeaveRequestActionController extends AbstractController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    #[Route('/app/leave-request/{id}/withdraw', name: 'app_leave_request_withdraw', methods: ['POST'])]
    public function withdraw(Request $request, string $id): Response
    {
        return $this->handleAction($request, $id, 'withdraw', function (LeaveRequestDTO $dto): void {
            $this->denyAccessUnlessGranted(LeaveRequestPermission::Withdraw->value, $dto);

            if (!in_array($dto->status, [LeaveRequestStatusEnum::Pending, LeaveRequestStatusEnum::Approved], true)) {
                throw new \DomainException('Only pending or approved requests can be withdrawn.');
            }

            $dto->status = LeaveRequestStatusEnum::Withdrawn;
            $this->leaveRequestFacade->updateAndRestoreBalanceIfNeeded($dto);
        });
    }

    #[Route('/app/leave-request/{id}/approve', name: 'app_leave_request_approve', methods: ['POST'])]
    public function approve(Request $request, string $id): Response
    {
        return $this->handleAction($request, $id, 'approve', function (LeaveRequestDTO $dto): void {
            $this->denyAccessUnlessGranted(LeaveRequestPermission::Manage->value, $dto);

            if (LeaveRequestStatusEnum::Pending !== $dto->status) {
                throw new \DomainException('Only pending requests can be approved.');
            }

            $dto->status = LeaveRequestStatusEnum::Approved;
            $dto->approvedBy = UserDTO::fromEntity($this->getCurrentUser());
            $this->leaveRequestFacade->update($dto);
        });
    }

    #[Route('/app/leave-request/{id}/reject', name: 'app_leave_request_reject', methods: ['POST'])]
    public function reject(Request $request, string $id): Response
    {
        return $this->handleAction($request, $id, 'reject', function (LeaveRequestDTO $dto): void {
            $this->denyAccessUnlessGranted(LeaveRequestPermission::Manage->value, $dto);

            if (LeaveRequestStatusEnum::Pending !== $dto->status) {
                throw new \DomainException('Only pending requests can be rejected.');
            }

            $dto->status = LeaveRequestStatusEnum::Rejected;
            $dto->approvedBy = UserDTO::fromEntity($this->getCurrentUser());
            $this->leaveRequestFacade->updateAndRestoreBalanceIfNeeded($dto);
        });
    }

    /**
     * @param \Closure(LeaveRequestDTO): void $action
     */
    private function handleAction(Request $request, string $id, string $actionName, \Closure $action): Response
    {
        $dto = $this->getLeaveRequestOrFail($id);
        $isJson = 'application/json' === $request->headers->get('Accept');

        $token = $request->request->get('_token') ?? $request->query->get('_token');
        if (!$this->isCsrfTokenValid(sprintf('%s%s', $actionName, $dto->id), is_string($token) ? $token : null)) {
            if ($isJson) {
                return $this->json(['success' => false, 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
            }

            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $action($dto);
        } catch (\DomainException $e) {
            if ($isJson) {
                return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('warning', $e->getMessage());

            return $this->redirectToDetail($dto);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            if ($isJson) {
                return $this->json(['success' => false, 'message' => $e->getMessage() ?: 'Access denied.'], Response::HTTP_FORBIDDEN);
            }

            throw $e;
        }

        if ($isJson) {
            return $this->json([
                'success' => true,
                'message' => sprintf('Leave request %s.', 'approve' === $actionName ? 'approved' : $actionName.'ed'),
                'status' => $dto->status->value,
                'id' => $dto->id->toString(),
            ]);
        }

        $this->addFlash('success', sprintf('Leave request %s.', 'approve' === $actionName ? 'approved' : $actionName.'ed'));

        return $this->redirectToDetail($dto);
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

    private function redirectToDetail(LeaveRequestDTO $dto): Response
    {
        return $this->redirect($this->urlGenerator->generate('app_dashboard_app_leave_request_detail', [
            'entityId' => $dto->id->toString(),
        ]));
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
