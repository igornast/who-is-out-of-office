<?php

declare(strict_types=1);

namespace App\Module\User\Controller;

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\Form\UserInvitationType;
use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Shared\DTO\InvitationDTO;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invitation/{token}', name: 'app_user_invitation', methods: ['GET', 'POST'])]
class InvitationController extends AbstractController
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitationRepository,
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function __invoke(string $token, Request $request): Response
    {
        $invitationDTO = $this->invitationRepository->findOneByToken($token);

        if (!$invitationDTO instanceof InvitationDTO) {
            throw new NotFoundHttpException();
        }

        $form = $this->createForm(UserInvitationType::class, new UserInvitationRequestDTO('', '', ''));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invitationRequestDTO = $form->getData();

            $this->userFacade->acceptUserInvitation($invitationRequestDTO, $invitationDTO);

            $this->addFlash('success', 'Account activated, please log in.');

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('@AppUser/invitation_accept.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
