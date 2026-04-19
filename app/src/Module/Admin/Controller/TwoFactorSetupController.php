<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Security\TotpSecretEncryptor;
use App\Module\Admin\DTO\TwoFactorSetupDTO;
use App\Module\Admin\Form\TwoFactorSetupVerifyType;
use App\Shared\Facade\UserFacadeInterface;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TwoFactorSetupController extends AbstractController
{
    private const SESSION_EXPIRY_SECONDS = 900;

    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TotpSecretEncryptor $encryptor,
        private readonly RateLimiterFactory $twoFactorLoginLimiter,
    ) {
    }

    #[Route('/app/settings/two-factor/setup', name: 'app_two_factor_setup')]
    public function setup(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTwoFactorEnabled) {
            $this->addFlash('info', 'settings.two_factor.info.already_enabled');

            return $this->redirectToRoute('app_settings_account_security');
        }

        $session = $request->getSession();
        $encryptedSecret = $session->get('2fa_setup_secret');
        $timestamp = $session->get('2fa_setup_timestamp', 0);

        if (null === $encryptedSecret || time() - $timestamp > self::SESSION_EXPIRY_SECONDS) {
            $session->remove('2fa_setup_secret');
            $session->remove('2fa_setup_timestamp');

            $totp = TOTP::generate();
            $secret = $totp->getSecret();
            $session->set('2fa_setup_secret', $this->encryptor->encrypt($secret));
            $session->set('2fa_setup_timestamp', time());
        } else {
            $secret = $this->encryptor->decrypt($encryptedSecret);
        }

        /** @var non-empty-string $secret */
        $totp = TOTP::createFromSecret($secret);
        // @phpstan-ignore argument.type
        $totp->setLabel($user->email);
        $totp->setIssuer("Who's OOO");
        $totp->setPeriod(30);
        $totp->setDigest('sha1');
        $totp->setDigits(6);

        $qrCodeUri = $totp->getProvisioningUri();

        $qrCode = new QrCode(
            data: $qrCodeUri,
            encoding: new Encoding('UTF-8'),
            size: 250,
            margin: 10,
        );

        $writer = new PngWriter();
        $qrCodeDataUri = $writer->write($qrCode)->getDataUri();

        $dto = new TwoFactorSetupDTO();
        $form = $this->createForm(TwoFactorSetupVerifyType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $this->twoFactorLoginLimiter->create($user->id->toString());
            $limit = $limiter->consume();

            if (!$limit->isAccepted()) {
                $this->addFlash('danger', 'two_factor_auth.error.too_many_attempts');

                return $this->redirectToRoute('app_settings_account_security');
            }

            if (!$this->passwordHasher->isPasswordValid($user, $dto->currentPassword)) {
                $this->addFlash('danger', 'settings.two_factor.setup.error.invalid_password');

                return $this->redirectToRoute('app_two_factor_setup');
            }

            // @phpstan-ignore argument.type
            if ($totp->verify($dto->verificationCode, null, 1)) {
                $limiter->reset();
                $recoveryCodes = $this->userFacade->enableTwoFactor($user->id->toString(), $secret);
                $session->remove('2fa_setup_secret');
                $session->remove('2fa_setup_timestamp');
                $session->set('2fa_recovery_codes', $recoveryCodes);

                return $this->redirectToRoute('app_two_factor_recovery_codes');
            }

            $this->addFlash('danger', 'settings.two_factor.setup.error.invalid_code');
        }

        $response = $this->render('@AppAdmin/settings/two_factor_setup.html.twig', [
            'form' => $form->createView(),
            'qrCodeDataUri' => $qrCodeDataUri,
            'secret' => $secret,
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    #[Route('/app/settings/two-factor/recovery-codes', name: 'app_two_factor_recovery_codes')]
    public function recoveryCodes(Request $request): Response
    {
        $session = $request->getSession();
        $codes = $session->get('2fa_recovery_codes');

        if (null === $codes) {
            return $this->redirectToRoute('app_settings_account_security');
        }

        $session->remove('2fa_recovery_codes');

        $response = $this->render('@AppAdmin/settings/two_factor_recovery_codes.html.twig', [
            'recoveryCodes' => $codes,
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
