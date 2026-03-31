<?php

declare(strict_types=1);

use App\Module\User\Controller\InvitationController;
use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Shared\DTO\InvitationDTO;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
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

beforeEach(function (): void {
    $this->invitationRepository = mock(InvitationRepositoryInterface::class);
    $this->userFacade = mock(UserFacadeInterface::class);

    $this->form = mock(FormInterface::class);
    $this->form->allows('createView')->andReturn(mock(FormView::class));

    $formFactory = mock(FormFactoryInterface::class);
    $formFactory->allows('create')->andReturn($this->form);

    $urlGenerator = mock(UrlGeneratorInterface::class);
    $urlGenerator->allows('generate')->andReturn('/redirect-url');

    $flashBag = mock(FlashBagInterface::class);
    $flashBag->allows('add');
    $session = mock(Session::class);
    $session->allows('getFlashBag')->andReturn($flashBag);
    $requestStack = new RequestStack();
    $req = Request::create('/invitation/some-token');
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

    $this->controller = new InvitationController(
        invitationRepository: $this->invitationRepository,
        userFacade: $this->userFacade,
    );
    $this->controller->setContainer($container);
});

function buildInvitationDTO(): InvitationDTO
{
    return new InvitationDTO(
        id: 'invitation-id',
        token: 'some-token',
        user: UserDTOFixture::create(),
        createdAt: new DateTimeImmutable(),
    );
}

it('throws 404 when invitation token is not found', function (): void {
    $this->invitationRepository->expects('findOneByToken')->with('invalid-token')->andReturn(null);

    ($this->controller)('invalid-token', Request::create('/invitation/invalid-token'));
})->throws(NotFoundHttpException::class);

it('renders invitation form on GET request', function (): void {
    $this->invitationRepository->expects('findOneByToken')->andReturn(buildInvitationDTO());
    $this->form->allows('handleRequest');
    $this->form->allows('isSubmitted')->andReturn(false);

    $response = ($this->controller)('some-token', Request::create('/invitation/some-token'));

    expect($response->getStatusCode())->toBe(200);
});

it('accepts invitation and redirects to dashboard after valid form submission', function (): void {
    $invitationDTO = buildInvitationDTO();
    $invitationRequestDTO = new UserInvitationRequestDTO('Jane', 'Smith', 'password123');

    $this->invitationRepository->expects('findOneByToken')->andReturn($invitationDTO);
    $this->form->allows('handleRequest');
    $this->form->allows('isSubmitted')->andReturn(true);
    $this->form->allows('isValid')->andReturn(true);
    $this->form->allows('getData')->andReturn($invitationRequestDTO);
    $this->userFacade->expects('acceptUserInvitation')->with($invitationRequestDTO, $invitationDTO)->once();

    $response = ($this->controller)('some-token', Request::create('/invitation/some-token', 'POST'));

    expect($response->getStatusCode())->toBe(302);
});
