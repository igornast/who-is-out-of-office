<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Form\UserProfileType;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Service\Ical\IcalHashGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/user/profile', name: 'app_user_profile')]
class UserProfileSettingsController extends AbstractController
{
    public function __construct(
        #[Autowire('%profile_images_base_path%')]
        private readonly string $profileImagesBasePath,
        #[Autowire('%kernel.project_dir%/public/%profile_images_base_path%')]
        private readonly string $uploadDirectory,
        #[Autowire(env: 'ICAL_SECRET')]
        private readonly string $icalSecret,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $removeRequested = '1' === $form->get('removeProfileImage')->getData();
            $uploadedFile = $form->get('profileImageFile')->getData();

            if ($removeRequested && null === $uploadedFile) {
                $this->userFacade->deleteOldProfileImage($user->profileImageUrl);
                $user->profileImageUrl = null;
            }

            if ($uploadedFile instanceof UploadedFile) {
                $this->userFacade->deleteOldProfileImage($user->profileImageUrl);

                $newFilename = sprintf('%s.%s', bin2hex(random_bytes(16)), $uploadedFile->guessExtension());
                $uploadedFile->move($this->uploadDirectory, $newFilename);
                $user->profileImageUrl = $newFilename;
            }

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Profile updated successfully!');

            return $this->redirectToRoute('app_user_profile');
        }

        $userDTO = UserDTO::fromEntity($user);
        $calendarSubscriptionUrl = $this->urlGenerator->generate('app_api_ical_endpoint', [
            'userId' => $user->id,
            'secret' => IcalHashGenerator::generateForUser($userDTO, $this->icalSecret),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('@AppAdmin/user/profile_edit.html.twig', [
            'form' => $form->createView(),
            'profile_images_base_path' => $this->profileImagesBasePath,
            'calendar_subscription_url' => $calendarSubscriptionUrl,
            'slack_connected' => null !== $user->slackIntegration,
            'slack_member_id' => $user->slackIntegration?->slackMemberId,
        ]);
    }
}
