<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\Api;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\UpdateThemePreferenceRequestDTO;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/app/api/user/theme', name: 'app_api_theme', methods: ['POST'])]
class ThemePreferenceController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        User $user,
        #[MapRequestPayload]
        UpdateThemePreferenceRequestDTO $dto,
    ): JsonResponse {
        $this->userFacade->updateThemePreference(
            $user->id->toString(),
            $dto->theme,
            $dto->palette,
        );

        return new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
    }
}
