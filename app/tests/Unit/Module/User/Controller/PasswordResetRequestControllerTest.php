<?php

declare(strict_types=1);

use App\Infrastructure\Email\Message\SendPasswordResetEmailMessage;
use App\Module\User\Controller\PasswordResetRequestController;
use App\Shared\Facade\EmailFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->emailFacade = mock(EmailFacadeInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);
    $this->logger = mock(LoggerInterface::class);

    $this->form = mock(FormInterface::class);
    $this->form->allows('createView')->andReturn(mock(FormView::class));

    $this->capturedDto = null;
    $formFactory = mock(FormFactoryInterface::class);
    $formFactory->allows('create')->andReturnUsing(function (string $type, mixed $data) {
        $this->capturedDto = $data;

        return $this->form;
    });

    $urlGenerator = mock(UrlGeneratorInterface::class);
    $urlGenerator->allows('generate')->andReturn('/redirect-url');

    $flashBag = mock(FlashBagInterface::class);
    $flashBag->allows('add');
    $session = mock(Session::class);
    $session->allows('getFlashBag')->andReturn($flashBag);
    $requestStack = new RequestStack();
    $req = Request::create('/password-reset/request');
    $req->setSession($session);
    $requestStack->push($req);

    $twig = mock(Twig\Environment::class);
    $twig->allows('render')->andReturn('<html></html>');

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('form.factory')->andReturn($formFactory);
    $container->allows('get')->with('router')->andReturn($urlGenerator);
    $container->allows('get')->with('request_stack')->andReturn($requestStack);
    $container->allows('get')->with('twig')->andReturn($twig);

    $this->controller = new PasswordResetRequestController(
        userFacade: $this->userFacade,
        emailFacade: $this->emailFacade,
        translator: $this->translator,
        logger: $this->logger,
    );
    $this->controller->setContainer($container);
});

it('renders password reset request form on GET request', function (): void {
    $this->form->allows('handleRequest');
    $this->form->allows('isSubmitted')->andReturn(false);

    $response = ($this->controller)(Request::create('/password-reset/request'));

    expect($response->getStatusCode())->toBe(200);
});

it('sends password reset email and redirects after valid form submission', function (): void {
    $this->form->allows('handleRequest')->andReturnUsing(function () {
        $this->capturedDto->email = 'user@example.com';

        return $this->form;
    });
    $this->form->allows('isSubmitted')->andReturn(true);
    $this->form->allows('isValid')->andReturn(true);
    $this->userFacade->expects('createPasswordResetToken')->with('user@example.com')->andReturn('reset-token-abc');
    $this->emailFacade->expects('sendPasswordResetEmail')->with('user@example.com', 'reset-token-abc')->once();

    $response = ($this->controller)(Request::create('/password-reset/request', 'POST'));

    expect($response->getStatusCode())->toBe(302);
});

it('does not send email when user is not found and still redirects', function (): void {
    $this->form->allows('handleRequest')->andReturnUsing(function () {
        $this->capturedDto->email = 'unknown@example.com';

        return $this->form;
    });
    $this->form->allows('isSubmitted')->andReturn(true);
    $this->form->allows('isValid')->andReturn(true);
    $this->userFacade->expects('createPasswordResetToken')->andReturn(null);
    $this->emailFacade->expects('sendPasswordResetEmail')->never();

    $response = ($this->controller)(Request::create('/password-reset/request', 'POST'));

    expect($response->getStatusCode())->toBe(302);
});

it('logs error and still redirects when email dispatch throws', function (): void {
    $this->form->allows('handleRequest')->andReturnUsing(function () {
        $this->capturedDto->email = 'user@example.com';

        return $this->form;
    });
    $this->form->allows('isSubmitted')->andReturn(true);
    $this->form->allows('isValid')->andReturn(true);
    $this->userFacade->expects('createPasswordResetToken')->andReturn('reset-token-abc');
    $this->emailFacade->expects('sendPasswordResetEmail')->andThrow(
        new HandlerFailedException(
            new Envelope(new SendPasswordResetEmailMessage('user@example.com', 'reset-token-abc')),
            [new RuntimeException('SMTP error')],
        )
    );
    $this->logger->expects('error')->once();

    $response = ($this->controller)(Request::create('/password-reset/request', 'POST'));

    expect($response->getStatusCode())->toBe(302);
});
