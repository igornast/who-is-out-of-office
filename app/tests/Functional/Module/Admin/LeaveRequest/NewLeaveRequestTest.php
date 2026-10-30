<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Facade\UserFacadeInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

beforeEach(function (): void {
    $kernel = static::bootKernel();
    $this->entityManager = $kernel->getContainer()
        ->get('doctrine')
        ->getManager();

    $this->user = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'user@whoisooo.app']);

    $this->vacationLeaveType = $this->entityManager
        ->getRepository(LeaveRequestType::class)
        ->findOneBy(['name' => 'Vacation']);
});

it('calculates workdays and submit a new request', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@whoisooo.app', '123');

    $crawler = $client->request('GET', '/app/dashboard/leave-requests/new');

    $startDate = new DateTimeImmutable('next Monday');
    $endDate = $startDate->modify('+4 days');
    $dateRangeString = $startDate->format('Y-m-d').' to '.$endDate->format('Y-m-d');

    $client->waitFor('#submit-btn');

    $form = $crawler->selectButton('submit-btn')->form();
    $form['new_leave_request_form[leaveType]'] = $this->vacationLeaveType->id->toString();
    $client->executeScript(sprintf(
        'const input = document.getElementById("new_leave_request_form_dateRange");
       input.value = "%s";
       input.dispatchEvent(new Event("input", { bubbles: true }));
       input.dispatchEvent(new Event("change", { bubbles: true }));',
        $dateRangeString
    ));

    $client->waitForVisibility('#infoBox');

    $infoBoxHtml = $client->getCrawler()->filter('#infoBox')->text();

    $expectedWorkdays = 5;
    $expectedRemainingBalance = $this->user->currentLeaveBalance - $expectedWorkdays;

    expect($infoBoxHtml)
        ->toContain((string) $expectedWorkdays)
        ->toContain('workdays will be taken')
        ->toContain('Remaining balance:')
        ->toContain((string) $expectedRemainingBalance);

    $submitButton = $client->getCrawler()->filter('#submit-btn');
    expect($submitButton->attr('disabled'))->toBeNull();

    $submitButton->click();
    //    $client->takeScreenshot('tests/_output/01-new-leave-request-form.png');

    $client->waitForVisibility('.alert-success');

    $successMessage = $client->getCrawler()->filter('.alert-success')->text();
    expect($successMessage)->toContain('The leave request has been created.');

    expect($client->getCurrentURL())->toContain('/app/dashboard/leave-request');
});

it('shows a translated error when the locked balance became insufficient after validation', function (): void {
    $client = static::createClient();
    $client->disableReboot();
    $container = $client->getContainer();
    $entityManager = $container->get('doctrine')->getManager();
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $leaveType = $entityManager->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Vacation']);
    expect($user)->toBeInstanceOf(User::class)
        ->and($leaveType)->toBeInstanceOf(LeaveRequestType::class);

    $user->currentLeaveBalance = 1;
    $user->absenceBalanceResetDay = new DateTimeImmutable('+6 months');
    $entityManager->flush();
    $container->get(UserFacadeInterface::class)->openInitialLeavePeriod($user->id->toString());
    $requestCountBefore = $entityManager->getRepository(LeaveRequest::class)->count([]);

    $userId = $user->id->toString();
    $listener = new class ($userId) {
        public function __construct(private readonly string $userId)
        {
        }

        public function prePersist(PrePersistEventArgs $event): void
        {
            if (!$event->getObject() instanceof LeaveRequest) {
                return;
            }

            $event->getObjectManager()->getConnection()->executeStatement(
                'UPDATE user SET current_leave_balance = 0 WHERE id = :id',
                ['id' => $this->userId],
            );
        }
    };
    $entityManager->getEventManager()->addEventListener([Events::prePersist], $listener);

    $client->loginUser($user);
    $crawler = $client->request('GET', '/app/dashboard/leave-requests/new');
    $date = new DateTimeImmutable('next Monday');
    $form = $crawler->selectButton('submit-btn')->form();
    $form['new_leave_request_form[leaveType]'] = $leaveType->id->toString();
    $form['new_leave_request_form[dateRange]'] = sprintf('%s to %s', $date->format('Y-m-d'), $date->format('Y-m-d'));
    $client->submit($form);

    expect($client->getResponse()->isRedirect())->toBeTrue();
    $crawler = $client->followRedirect();
    $entityManager->flush();

    expect($crawler->text())->toContain('Your leave balance is insufficient for this request.')
        ->and($entityManager->getRepository(LeaveRequest::class)->count([]))->toBe($requestCountBefore);
});
