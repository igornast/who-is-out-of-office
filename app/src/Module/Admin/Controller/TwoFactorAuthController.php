<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/2fa', name: '2fa_login')]
class TwoFactorAuthController extends AbstractController
{
    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();

        $response = $this->render('@AppAdmin/page/two_factor_auth.html.twig', [
            'error' => $error,
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
