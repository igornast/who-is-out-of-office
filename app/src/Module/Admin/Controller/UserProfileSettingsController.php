<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/user/profile', name: 'app_user_profile')]
class UserProfileSettingsController extends AbstractController
{
    public function __construct(
        #[Autowire('%profile_images_base_path%')]
        private readonly string $profileImagesBasePath,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $plainPassword = $form->get('plainPassword')->getData();
            if (is_string($plainPassword)) {
                $hashPassword = $this->hasher->hashPassword($user, $plainPassword);

                $user->password = $hashPassword;
            }

            $uploadedFile = $form->get('profileImageFile')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                $newFilename = uniqid().'.'.$uploadedFile->guessExtension();
                $uploadedFile->move('uploads/profile_images', $newFilename);

                $user->profileImageUrl = $newFilename;
            }

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Profile updated successfully!');

            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('@AppAdmin/user/profile_edit.html.twig', [
            'form' => $form->createView(),
            'profile_images_base_path' => $this->profileImagesBasePath,
        ]);
    }
}
