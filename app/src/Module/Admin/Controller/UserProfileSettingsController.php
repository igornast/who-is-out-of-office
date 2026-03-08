<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\UserProfileDTO;
use App\Module\Admin\Form\UserProfileType;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Service\Ical\IcalSubscriptionUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
        private readonly EntityManagerInterface $em,
        private readonly UserFacadeInterface $userFacade,
        private readonly IcalSubscriptionUrlGenerator $icalSubscriptionUrlGenerator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $dto = UserProfileDTO::fromUser($user);
        $form = $this->createForm(UserProfileType::class, $dto);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($dto->isRemoveProfileImageRequested()) {
                $this->userFacade->deleteOldProfileImage($user->profileImageUrl);
                $user->profileImageUrl = null;
            }

            if ($dto->profileImageFile instanceof UploadedFile) {
                $this->userFacade->deleteOldProfileImage($user->profileImageUrl);

                $newFilename = sprintf('%s.%s', bin2hex(random_bytes(16)), $dto->profileImageFile->guessExtension());
                $dto->profileImageFile->move($this->uploadDirectory, $newFilename);
                $user->profileImageUrl = $newFilename;
            }

            $user->firstName = $dto->firstName;
            $user->lastName = $dto->lastName;
            $user->workingDays = $dto->workingDays;
            $user->holidayCalendar = $dto->holidayCalendar;
            $user->birthDate = $dto->birthDate;
            $user->hasCelebrateWorkAnniversary = $dto->hasCelebrateWorkAnniversary;

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Profile updated successfully!');

            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('@AppAdmin/user/profile_edit.html.twig', [
            'form' => $form->createView(),
            'profile_images_base_path' => $this->profileImagesBasePath,
            'calendar_subscription_url' => $this->icalSubscriptionUrlGenerator->generateForUser(UserDTO::fromEntity($user)),
            'slack_connected' => null !== $user->slackIntegration,
            'slack_member_id' => $user->slackIntegration?->slackMemberId,
        ]);
    }
}
