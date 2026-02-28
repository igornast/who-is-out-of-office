<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('@AppAdmin/page/login.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
            'page_title' => 'Who\'s OOO — Sign In',
            'csrf_token_intention' => 'authenticate',
            'target_path' => $this->generateUrl('app_dashboard'),
        ]);
    }
}
