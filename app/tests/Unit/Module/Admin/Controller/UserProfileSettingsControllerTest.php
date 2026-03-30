<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\UserProfileSettingsController;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Service\Ical\IcalSubscriptionUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->em = mock(EntityManagerInterface::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->userFacade->allows('deleteOldProfileImage');
    $this->icalUrlGenerator = mock(IcalSubscriptionUrlGenerator::class);
    $this->icalUrlGenerator->allows('generateForUser')->andReturn('https://example.com/cal.ics');
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);
    $this->holidayFacade = mock(HolidayFacadeInterface::class);
    $this->holidayFacade->allows('getSubdivisionsGroupedByCalendar')->andReturn([]);

    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@whoisooo.app',
        password: 'hashed',
        workingDays: [1, 2, 3, 4, 5],
        isEmailNotificationsEnabled: false,
    );

    $token = mock(TokenInterface::class);
    $token->allows('getUser')->andReturn($this->user);

    $tokenStorage = mock(TokenStorageInterface::class);
    $tokenStorage->allows('getToken')->andReturn($token);

    $this->authChecker = mock(AuthorizationCheckerInterface::class);

    $this->form = mock(FormInterface::class);
    $this->form->allows('createView')->andReturn(mock(FormView::class));

    $formFactory = mock(FormFactoryInterface::class);
    $formFactory->allows('create')->andReturnUsing(function (string $type, mixed $data) {
        $this->capturedDto = $data;

        return $this->form;
    });

    $urlGenerator = mock(UrlGeneratorInterface::class);
    $urlGenerator->allows('generate')->andReturn('/app/user/profile');

    $flashBag = mock(FlashBagInterface::class);
    $flashBag->allows('add');
    $session = mock(Session::class);
    $session->allows('getFlashBag')->andReturn($flashBag);

    $requestStack = new RequestStack();
    $request = Request::create('/app/user/profile');
    $request->setSession($session);
    $requestStack->push($request);

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('security.authorization_checker')->andReturn($this->authChecker);
    $container->allows('get')->with('security.token_storage')->andReturn($tokenStorage);
    $container->allows('get')->with('form.factory')->andReturn($formFactory);
    $container->allows('get')->with('router')->andReturn($urlGenerator);
    $container->allows('get')->with('request_stack')->andReturn($requestStack);

    $this->controller = new UserProfileSettingsController(
        profileImagesBasePath: 'uploads/profile_images',
        uploadDirectory: '/tmp/uploads',
        em: $this->em,
        userFacade: $this->userFacade,
        holidayFacade: $this->holidayFacade,
        icalSubscriptionUrlGenerator: $this->icalUrlGenerator,
        translator: $this->translator,
    );
    $this->controller->setContainer($container);
});

it('maps isEmailNotificationsEnabled from DTO to entity on form submit', function (): void {
    $this->form->allows('handleRequest')->andReturnUsing(function () {
        $this->capturedDto->isEmailNotificationsEnabled = true;

        return $this->form;
    });
    $this->form->allows('isSubmitted')->andReturn(true);
    $this->form->allows('isValid')->andReturn(true);

    $this->em->expects('persist')->once();
    $this->em->expects('flush')->once();

    ($this->controller)(Request::create('/app/user/profile', 'POST'));

    expect($this->user->isEmailNotificationsEnabled)->toBeTrue();
});

it('maps subdivisionCode from DTO to entity on form submit', function (): void {
    $this->form->allows('handleRequest')->andReturnUsing(function () {
        $this->capturedDto->subdivisionCode = 'DE-BY';

        return $this->form;
    });
    $this->form->allows('isSubmitted')->andReturn(true);
    $this->form->allows('isValid')->andReturn(true);

    $this->em->expects('persist')->once();
    $this->em->expects('flush')->once();

    expect($this->user->subdivisionCode)->toBeNull();

    ($this->controller)(Request::create('/app/user/profile', 'POST'));

    expect($this->user->subdivisionCode)->toBe('DE-BY');
});
