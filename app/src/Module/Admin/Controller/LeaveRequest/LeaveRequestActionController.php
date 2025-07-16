<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\LeaveRequestCalculateRequestDTO;
use App\Module\Admin\DTO\LeaveRequestDraftDTO;
use App\Module\Admin\Form\LeaveRequestDraftType;
use App\Shared\DTO\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\DTO\LeaveRequest\Query\CalculateWorkdaysQuery;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class LeaveRequestActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UserFacadeInterface $userFacade,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
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

    #[Route('/app/leave-request/new', name: 'app_leave_request_new')]
    public function new(Request $request): Response
    {
        $dto = new LeaveRequestDraftDTO();
        $form = $this->createForm(LeaveRequestDraftType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $this->leaveRequestFacade->save(new SaveLeaveRequestCommand(
                $dto->leaveType,
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

        return $this->render('@AppAdmin/leave_request/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('app/leave-request/calculate', name: 'app_leave_request_calculate', methods: ['POST'])]
    public function confirm(
        #[CurrentUser]
        User $user,
        #[MapRequestPayload]
        LeaveRequestCalculateRequestDTO $requestDTO,
    ): Response {
        $start = new \DateTimeImmutable($requestDTO->startDate);
        $end = new \DateTimeImmutable($requestDTO->endDate);

        $query = new CalculateWorkdaysQuery(
            startDate: $start,
            endDate: $end,
            userWorkingDays: $user->workingDays,
            holidayCalendarCountryCode: $user->holidayCalendar?->countryCode
        );

        $workingDays = $this->leaveRequestFacade->calculateWorkDays($query);
        $remainingBalance = $user->currentLeaveBalance - $workingDays;

        return $this->json([
            'workdays' => $workingDays,
            'remainingBalance' => max($remainingBalance, 0),
        ]);
    }
}
