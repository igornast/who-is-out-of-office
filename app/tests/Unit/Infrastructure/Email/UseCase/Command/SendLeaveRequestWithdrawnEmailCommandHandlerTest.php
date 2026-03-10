<?php

declare(strict_types=1);

use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestWithdrawnEmailCommandHandler;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->mailer = mock(MailerInterface::class);
    $this->urlGenerator = mock(UrlGeneratorInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->logger = mock(LoggerInterface::class);

    $this->handler = new SendLeaveRequestWithdrawnEmailCommandHandler(
        emailFromAddress: 'noreply@whosooo.com',
        emailFromName: "Who's OOO",
        mailer: $this->mailer,
        urlGenerator: $this->urlGenerator,
        translator: $this->translator,
        userFacade: $this->userFacade,
        logger: $this->logger,
    );
});

it('sends email to manager', function () {
    $manager = UserDTOFixture::create(['email' => 'manager@example.com']);
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['managerId' => $manager->id]),
    ]);

    $this->userFacade->expects('getUser')->with($manager->id)->andReturn($manager);
    $this->urlGenerator->expects('generate')->andReturn('https://example.com/dashboard');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            expect($email->getTo()[0]->getAddress())->toBe('manager@example.com');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('uses correct template', function () {
    $manager = UserDTOFixture::create();
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['managerId' => $manager->id]),
    ]);

    $this->userFacade->expects('getUser')->andReturn($manager);
    $this->urlGenerator->expects('generate')->andReturn('https://example.com/dashboard');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            expect($email->getHtmlTemplate())->toBe('@AppEmail/leave_request_withdrawn.html.twig');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('includes employee name in context', function () {
    $manager = UserDTOFixture::create();
    $user = UserDTOFixture::create([
        'firstName' => 'John',
        'lastName' => 'Doe',
        'managerId' => $manager->id,
    ]);
    $leaveRequestDTO = LeaveRequestDTOFixture::create(['user' => $user]);

    $this->userFacade->expects('getUser')->andReturn($manager);
    $this->urlGenerator->expects('generate')->andReturn('https://example.com/dashboard');

    $this->mailer
        ->expects('send')
        ->once()
        ->withArgs(function (TemplatedEmail $email) {
            expect($email->getContext()['employee_name'])->toBe('John Doe');

            return true;
        });

    $this->handler->handle($leaveRequestDTO);
});

it('skips when user has no manager', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['managerId' => null]),
    ]);

    $this->mailer->expects('send')->never();

    $this->handler->handle($leaveRequestDTO);
});

it('skips when manager has email notifications disabled', function () {
    $manager = UserDTOFixture::create(['isEmailNotificationsEnabled' => false]);
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['managerId' => $manager->id]),
    ]);

    $this->userFacade->expects('getUser')->with($manager->id)->andReturn($manager);
    $this->mailer->expects('send')->never();

    $this->handler->handle($leaveRequestDTO);
});

it('logs error when mailer transport fails', function () {
    $manager = UserDTOFixture::create();
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'user' => UserDTOFixture::create(['managerId' => $manager->id]),
    ]);

    $this->userFacade->expects('getUser')->andReturn($manager);
    $this->urlGenerator->expects('generate')->andReturn('https://example.com/dashboard');

    $this->mailer
        ->expects('send')
        ->andThrow(new TransportException('SMTP connection refused'));

    $this->logger
        ->expects('error')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'SMTP connection refused'));

    $this->handler->handle($leaveRequestDTO);
});
