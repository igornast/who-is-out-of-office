<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\UserFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class LeaveRequestActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    #[Route('/app/leave-request/{id}/withdraw', name: 'app_leave_request_withdraw')]
    public function withdraw(LeaveRequest $leaveRequest): RedirectResponse
    {

        if (!in_array($leaveRequest->status, [LeaveRequestStatusEnum::Pending, LeaveRequestStatusEnum::Approved])) {
            $this->addFlash('warning', 'Only pending requests can be withdrawn.');
        }

        $leaveRequest->status = LeaveRequestStatusEnum::Withdrawn;
        $this->em->flush();

        $this->userFacade->updateUserCurrentLeaveBalance($leaveRequest->user->id->toString(), $leaveRequest->workDays);

        $this->addFlash('success', 'Leave request withdrawn.');

        $url = $this->adminUrlGenerator
            ->setController(LeaveRequestCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($leaveRequest->id)
            ->generateUrl();

        return $this->redirect($url);
    }
}
