<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;

beforeEach(function (): void {
    $kernel = static::bootKernel();
    $this->entityManager = $kernel->getContainer()
        ->get('doctrine')
        ->getManager();

    $this->user = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'user@ooo.com']);

    $this->vacationLeaveType = $this->entityManager
        ->getRepository(LeaveRequestType::class)
        ->findOneBy(['name' => 'Vacation']);
});

it('calculates workdays and submit a new request', function (): void {
    $this->entityManager->flush();

    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@ooo.com', '123');

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
    $expectedRemainingBalance = 19;

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
