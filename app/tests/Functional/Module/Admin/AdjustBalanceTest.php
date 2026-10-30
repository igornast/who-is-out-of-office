<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Facade\UserFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    self::bootKernel();
    $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    $this->userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $this->admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->target = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
});

it('records a manual adjustment with a reason and updates the balance', function (): void {
    expect($this->admin)->toBeInstanceOf(User::class)
        ->and($this->target)->toBeInstanceOf(User::class);
    $targetId = $this->target->id->toString();
    $reason = sprintf('Compensation for on-call weekend %s', Uuid::uuid4()->toString());
    $this->userFacade->openInitialLeavePeriod($targetId);

    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $targetId));

    expect($client->getResponse()->getStatusCode())->toBe(200);

    $form = $crawler->selectButton('Record adjustment')->form();
    $form['adjust_balance_form[delta]'] = '3';
    $form['adjust_balance_form[reason]'] = $reason;
    $client->submit($form);

    expect($client->getResponse()->isRedirect('/app/my-organization/'.$targetId.'/balance-history'))->toBeTrue();

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(User::class, $targetId);
    $entries = $this->entityManager->getRepository(BalanceLedgerEntry::class)->findBy([
        'user' => $targetId,
        'type' => BalanceLedgerEntryTypeEnum::ManualAdjustment,
        'reason' => $reason,
    ]);

    expect($reloaded)->toBeInstanceOf(User::class)
        ->and($entries)->toHaveCount(1)
        ->and($reloaded->currentLeaveBalance)->toBe($entries[0]->balanceAfter)
        ->and($entries[0]->balanceAfter)->toBe($entries[0]->balanceBefore + 3)
        ->and($entries[0]->reason)->toBe($reason)
        ->and($entries[0]->delta)->toBe(3)
        ->and($entries[0]->source)->toBe(BalanceLedgerSourceEnum::App)
        ->and($entries[0]->actor?->id->toString())->toBe($this->admin->id->toString());
});

it('rejects zero adjustments and blank reasons without changing the balance', function (): void {
    expect($this->admin)->toBeInstanceOf(User::class)
        ->and($this->target)->toBeInstanceOf(User::class);
    $targetId = $this->target->id->toString();
    $balanceBefore = $this->target->currentLeaveBalance;

    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $targetId));
    $form = $crawler->selectButton('Record adjustment')->form();
    $form['adjust_balance_form[delta]'] = '0';
    $form['adjust_balance_form[reason]'] = '';
    $crawler = $client->submit($form);

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(User::class, $targetId);

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('.invalid-feedback')->text())->toContain('An adjustment must change the balance.')
        ->and($crawler->text())->toContain('This value should not be blank.')
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->currentLeaveBalance)->toBe($balanceBefore);
});

it('denies the adjustment page to non-admin users', function (): void {
    expect($this->target)->toBeInstanceOf(User::class)
        ->and($this->admin)->toBeInstanceOf(User::class);

    $client = static::createClient();
    $client->loginUser($this->target);
    $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $this->admin->id->toString()));

    expect($client->getResponse()->getStatusCode())->toBe(403);
});

it('rejects a whitespace-only reason without changing the balance', function (): void {
    expect($this->admin)->toBeInstanceOf(User::class)
        ->and($this->target)->toBeInstanceOf(User::class);
    $targetId = $this->target->id->toString();
    $balanceBefore = $this->target->currentLeaveBalance;

    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $targetId));
    $form = $crawler->selectButton('Record adjustment')->form();
    $form['adjust_balance_form[delta]'] = '1';
    $form['adjust_balance_form[reason]'] = '   ';
    $crawler = $client->submit($form);

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(User::class, $targetId);

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->text())->toContain('This value should not be blank.')
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->currentLeaveBalance)->toBe($balanceBefore);
});

it('rejects reasons longer than 255 characters without changing the balance', function (): void {
    expect($this->admin)->toBeInstanceOf(User::class)
        ->and($this->target)->toBeInstanceOf(User::class);
    $targetId = $this->target->id->toString();
    $balanceBefore = $this->target->currentLeaveBalance;

    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $targetId));
    $form = $crawler->selectButton('Record adjustment')->form();
    $form['adjust_balance_form[delta]'] = '1';
    $form['adjust_balance_form[reason]'] = str_repeat('x', 256);
    $crawler = $client->submit($form);

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(User::class, $targetId);

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->text())->toContain('This value is too long.')
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->currentLeaveBalance)->toBe($balanceBefore);
});

it('rejects an adjustment outside the allowed range without a server error', function (): void {
    $targetId = $this->target->id->toString();
    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $targetId));
    $form = $crawler->selectButton('Record adjustment')->form();
    $form['adjust_balance_form[delta]'] = '366';
    $form['adjust_balance_form[reason]'] = 'Too large';
    $crawler = $client->submit($form);

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->text())->toContain('Adjustment must be between -365 and 365 days.');

    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $targetId));
    $form = $crawler->selectButton('Record adjustment')->form();
    $form['adjust_balance_form[delta]'] = '-366';
    $form['adjust_balance_form[reason]'] = 'Too small';
    $crawler = $client->submit($form);

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->text())->toContain('Adjustment must be between -365 and 365 days.');
});

it('rejects decimal adjustments', function (): void {
    $targetId = $this->target->id->toString();
    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $targetId));
    $form = $crawler->selectButton('Record adjustment')->form();
    $form['adjust_balance_form[delta]'] = '1.5';
    $form['adjust_balance_form[reason]'] = 'Decimal';
    $crawler = $client->submit($form);

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->text())->toContain('Please enter an integer.');
});

it('accepts a negative adjustment that takes the balance below zero', function (): void {
    $targetId = $this->target->id->toString();
    $this->userFacade->openInitialLeavePeriod($targetId);
    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('GET', sprintf('/app/my-organization/%s/adjust-balance', $targetId));
    $form = $crawler->selectButton('Record adjustment')->form();
    $form['adjust_balance_form[delta]'] = '-30';
    $form['adjust_balance_form[reason]'] = 'Correction';
    $client->submit($form);

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(User::class, $targetId);

    expect($client->getResponse()->isRedirect('/app/my-organization/'.$targetId.'/balance-history'))->toBeTrue();
    expect($reloaded->currentLeaveBalance)->toBeLessThan(0);
});

it('rejects a submission with an invalid CSRF token', function (): void {
    expect($this->admin)->toBeInstanceOf(User::class)
        ->and($this->target)->toBeInstanceOf(User::class);
    $targetId = $this->target->id->toString();
    $balanceBefore = $this->target->currentLeaveBalance;

    $client = static::createClient();
    $client->loginUser($this->admin);
    $crawler = $client->request('POST', sprintf('/app/my-organization/%s/adjust-balance', $targetId), [
        'adjust_balance_form' => [
            'delta' => '2',
            'reason' => 'Invalid token',
            '_token' => 'invalid',
        ],
    ]);

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(User::class, $targetId);

    expect($client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->text())->toContain('CSRF token is invalid')
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->currentLeaveBalance)->toBe($balanceBefore);
});

it('denies adjustment submissions from non-admin users', function (): void {
    expect($this->target)->toBeInstanceOf(User::class)
        ->and($this->admin)->toBeInstanceOf(User::class);

    $client = static::createClient();
    $client->loginUser($this->target);
    $client->request('POST', sprintf('/app/my-organization/%s/adjust-balance', $this->admin->id->toString()), [
        'adjust_balance_form' => [
            'delta' => '2',
            'reason' => 'Unauthorized',
        ],
    ]);

    expect($client->getResponse()->getStatusCode())->toBe(403);
});
