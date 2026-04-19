<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Security\TotpSecretEncryptor;
use App\Module\Admin\DTO\TwoFactorDisableDTO;
use App\Module\Admin\Form\TwoFactorDisableType;
use App\Shared\Facade\UserFacadeInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/settings/two-factor/disable', name: 'app_two_factor_disable')]
class TwoFactorDisableController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TotpSecretEncryptor $encryptor,
        private readonly RateLimiterFactory $twoFactorLoginLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isTwoFactorEnabled) {
            return $this->redirectToRoute('app_settings_account_security');
        }

        $dto = new TwoFactorDisableDTO();
        $form = $this->createForm(TwoFactorDisableType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $this->twoFactorLoginLimiter->create($user->id->toString());
            $limit = $limiter->consume();

            if (!$limit->isAccepted()) {
                $this->addFlash('danger', 'two_factor_auth.error.too_many_attempts');

                return $this->redirectToRoute('app_settings_account_security');
            }

            if (!$this->passwordHasher->isPasswordValid($user, $dto->password)) {
                $this->addFlash('danger', 'settings.two_factor.disable.error.invalid_password');

                return $this->redirectToRoute('app_two_factor_disable');
            }

            if (null === $user->totpSecret) {
                return $this->redirectToRoute('app_settings_account_security');
            }

            $decryptedSecret = $this->encryptor->decrypt($user->totpSecret);

            /** @var non-empty-string $decryptedSecret */
            $totp = TOTP::createFromSecret($decryptedSecret);
            $totp->setPeriod(30);
            $totp->setDigest('sha1');
            $totp->setDigits(6);

            // @phpstan-ignore argument.type
            if (!$totp->verify($dto->totpCode, null, 1)) {
                $this->addFlash('danger', 'settings.two_factor.disable.error.invalid_code');

                return $this->redirectToRoute('app_two_factor_disable');
            }

            $limiter->reset();
            $this->userFacade->disableTwoFactor($user->id->toString());

            $this->addFlash('success', 'settings.two_factor.status.disabled_success');

            return $this->redirectToRoute('app_settings_account_security');
        }

        $response = $this->render('@AppAdmin/settings/two_factor_disable.html.twig', [
            'form' => $form->createView(),
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
