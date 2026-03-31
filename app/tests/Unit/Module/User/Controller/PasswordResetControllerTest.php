<?php

declare(strict_types=1);

use App\Module\User\Controller\PasswordResetController;
use App\Shared\DTO\PasswordResetTokenDTO;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);
    $this->clock = mock(ClockInterface::class);
    $this->clock->allows('now')->andReturn(new DateTimeImmutable());

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
    $req = Request::create('/password-reset/valid-token');
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

    $this->controller = new PasswordResetController(
        userFacade: $this->userFacade,
        translator: $this->translator,
        clock: $this->clock,
    );
    $this->controller->setContainer($container);
});

function buildValidPasswordResetTokenDTO(): PasswordResetTokenDTO
{
    return new PasswordResetTokenDTO(
        id: 'token-id',
        token: 'valid-token',
        user: UserDTOFixture::create(),
        expiresAt: new DateTimeImmutable('+1 hour'),
        createdAt: new DateTimeImmutable(),
    );
}

it('throws 404 when token is not found', function (): void {
    $this->userFacade->expects('getPasswordResetToken')->with('valid-token')->andReturn(null);

    ($this->controller)('valid-token', Request::create('/password-reset/valid-token'));
})->throws(NotFoundHttpException::class);

it('throws 404 when token is expired', function (): void {
    $expiredToken = new PasswordResetTokenDTO(
        id: 'token-id',
        token: 'valid-token',
        user: UserDTOFixture::create(),
        expiresAt: new DateTimeImmutable('-1 hour'),
        createdAt: new DateTimeImmutable(),
    );
    $this->userFacade->expects('getPasswordResetToken')->andReturn($expiredToken);

    ($this->controller)('valid-token', Request::create('/password-reset/valid-token'));
})->throws(NotFoundHttpException::class);

it('renders password reset form on GET request', function (): void {
    $this->userFacade->expects('getPasswordResetToken')->andReturn(buildValidPasswordResetTokenDTO());
    $this->form->allows('handleRequest');
    $this->form->allows('isSubmitted')->andReturn(false);

    $response = ($this->controller)('valid-token', Request::create('/password-reset/valid-token'));

    expect($response->getStatusCode())->toBe(200);
});

it('redirects to login after successful password reset', function (): void {
    $this->userFacade->expects('getPasswordResetToken')->andReturn(buildValidPasswordResetTokenDTO());
    $this->form->allows('handleRequest')->andReturnUsing(function () {
        $this->capturedDto->password = 'new-secure-password';

        return $this->form;
    });
    $this->form->allows('isSubmitted')->andReturn(true);
    $this->form->allows('isValid')->andReturn(true);
    $this->userFacade->expects('resetPassword')->with('valid-token', 'new-secure-password')->andReturn(true);

    $response = ($this->controller)('valid-token', Request::create('/password-reset/valid-token', 'POST'));

    expect($response->getStatusCode())->toBe(302);
});

it('redirects to reset request page after failed password reset', function (): void {
    $this->userFacade->expects('getPasswordResetToken')->andReturn(buildValidPasswordResetTokenDTO());
    $this->form->allows('handleRequest')->andReturnUsing(function () {
        $this->capturedDto->password = 'new-secure-password';

        return $this->form;
    });
    $this->form->allows('isSubmitted')->andReturn(true);
    $this->form->allows('isValid')->andReturn(true);
    $this->userFacade->expects('resetPassword')->andReturn(false);

    $response = ($this->controller)('valid-token', Request::create('/password-reset/valid-token', 'POST'));

    expect($response->getStatusCode())->toBe(302);
});
