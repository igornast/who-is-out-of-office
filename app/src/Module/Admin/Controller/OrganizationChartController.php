<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/organization-chart', name: 'app_organization_chart')]
class OrganizationChartController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('@AppAdmin/organization_chart.html.twig', [
            'tree' => $this->userFacade->getOrganizationTree(),
        ]);
    }
}
