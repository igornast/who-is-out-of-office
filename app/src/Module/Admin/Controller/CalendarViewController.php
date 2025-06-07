<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/calendar', name: 'app_calendar_view')]
class CalendarViewController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('@AppAdmin/calendar_view.html.twig');
    }
}
