<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverDimension;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    static::bootKernel();

    $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    $this->manager = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'elena.novak@whoisooo.app']);
    $this->regularUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $leaveType = $this->entityManager->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Sick Leave']);

    $this->pendingRequest = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $this->regularUser,
        status: LeaveRequestStatusEnum::Pending,
        leaveType: $leaveType,
        startDate: new DateTimeImmutable('+30 days'),
        endDate: new DateTimeImmutable('+34 days'),
        workDays: 5,
    );
    $this->entityManager->persist($this->pendingRequest);
    $this->entityManager->flush();
});

afterEach(function (): void {
    if (isset($this->pendingRequest)) {
        $request = $this->entityManager->find(LeaveRequest::class, $this->pendingRequest->id);
        if ($request instanceof LeaveRequest) {
            $this->entityManager->remove($request);
            $this->entityManager->flush();
        }
    }

    self::ensureKernelShutdown();
});

it('opens the organization row actions menu in the browser', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->request('GET', '/app/dashboard/my-organization');
    $client->waitForVisibility('.dropdown-actions > .dropdown-toggle');

    expect((bool) $client->executeScript(
        "const icon = document.querySelector('.dropdown-actions > .dropdown-toggle .icon svg'); return !!icon && icon.getBoundingClientRect().height >= 12;"
    ))->toBeTrue();

    $client->getCrawler()->filter('.dropdown-actions > .dropdown-toggle')->click();
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "const menu = document.querySelector('.dropdown-actions .dropdown-menu'); return !!menu && menu.classList.contains('show');"
    ));

    expect($client->getCrawler()->filter('.dropdown-actions .dropdown-menu .action-edit')->count())->toBeGreaterThan(0);
});

it('lets a manager approve a selected team request in the browser', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'elena.novak@whoisooo.app', '123');

    $client->request('GET', '/app/dashboard/team/leave-requests');
    $client->waitForVisibility('.form-batch-checkbox');

    foreach (['light', 'dark'] as $mode) {
        $client->executeScript(sprintf(
            "localStorage.setItem('whosooo-theme', '%s'); document.documentElement.classList.toggle('dark', %s); document.documentElement.classList.toggle('ea-dark-scheme', %s); document.documentElement.dataset.bsTheme = '%s'; document.documentElement.style.colorScheme = '%s';",
            $mode,
            'dark' === $mode ? 'true' : 'false',
            'dark' === $mode ? 'true' : 'false',
            'dark' === $mode ? 'dark' : 'light',
            'dark' === $mode ? 'dark' : 'light',
        ));
        usleep(250000);

        $contrast = $client->executeScript(
            "const checkbox = document.querySelector('[value=\"{$this->pendingRequest->id}\"]');
            if (!checkbox) return {ratio: 0};
            const parseColor = (color) => {
                const match = color.match(/rgba?\\((\\d+),\\s*(\\d+),\\s*(\\d+)(?:,\\s*([\\d.]+))?\\)/);
                if (!match || (match[4] !== undefined && Number(match[4]) === 0)) return null;
                return [Number(match[1]), Number(match[2]), Number(match[3])];
            };
            const backgroundColor = (element) => {
                for (let node = element; node; node = node.parentElement) {
                    const color = parseColor(getComputedStyle(node).backgroundColor);
                    if (color) return color;
                }
                return [255, 255, 255];
            };
            const luminance = (color) => color.map((channel) => channel / 255).map((channel) => channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4)).reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
            const border = parseColor(getComputedStyle(checkbox).borderTopColor);
            const background = backgroundColor(checkbox.closest('td'));
            if (!border || checkbox.checked) return {ratio: 0};
            const borderLuminance = luminance(border);
            const backgroundLuminance = luminance(background);
            return {ratio: (Math.max(borderLuminance, backgroundLuminance) + 0.05) / (Math.min(borderLuminance, backgroundLuminance) + 0.05)};"
        );

        expect($contrast['ratio'] ?? 0)->toBeGreaterThanOrEqual(3.0);
    }

    $client->getCrawler()->filter(sprintf('[value="%s"]', $this->pendingRequest->id))->click();
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "const action = document.querySelector('[data-action-batch=\"true\"]'); return !!action && !document.querySelector('.batch-actions').inert;"
    ));

    expect((bool) $client->executeScript(
        "return document.querySelector('.page-actions-stack').classList.contains('rows-are-selected');"
    ))->toBeTrue();

    $client->getCrawler()->filter('[data-action-batch="true"]')->click();
    $client->waitForVisibility('#modal-batch-action');
    expect($client->getCrawler()->filter('#batch-action-confirmation-title')->text())->not->toBeEmpty();

    $client->getCrawler()->filter('#modal-batch-action-button')->click();
    $client->wait(10)->until(static fn (): bool => (bool) $client->executeScript(
        "return document.querySelector('#modal-batch-action') === null || !document.querySelector('#modal-batch-action').classList.contains('show');"
    ));

    $this->entityManager->clear();
    $updatedRequest = $this->entityManager->find(LeaveRequest::class, $this->pendingRequest->id);
    expect($updatedRequest->status)->toBe(LeaveRequestStatusEnum::Approved);
});

it('opens the sidebar and follows a navigation link on a mobile viewport', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');
    $client->manage()->window()->setSize(new WebDriverDimension(390, 844));

    try {
        $client->request('GET', '/app/dashboard');
        $client->waitForVisibility('.ooo-mobile-toggle');
        expect($client->getCrawler()->filter('.ooo-mobile-toggle')->isDisplayed())->toBeTrue();

        $client->getCrawler()->filter('.ooo-mobile-toggle')->click();
        $client->waitForVisibility('.ooo-sidebar-item[href$="/app/dashboard"]');
        expect($client->getCrawler()->filter('.ooo-sidebar-item[href$="/app/dashboard"]')->isDisplayed())->toBeTrue();
        $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
            "return document.querySelector('.ooo-mobile-toggle').getAttribute('aria-expanded') === 'true' && document.activeElement.closest('.ooo-sidebar') !== null;"
        ));

        $client->executeScript("window.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));");
        $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
            "return document.querySelector('.ooo-mobile-toggle').getAttribute('aria-expanded') === 'false' && document.activeElement.classList.contains('ooo-mobile-toggle');"
        ));

        $client->getCrawler()->filter('.ooo-mobile-toggle')->click();
        $client->waitForVisibility('.ooo-sidebar-item[href*="/app/dashboard/my-organization"]');
        $client->getCrawler()->filter('.ooo-sidebar-item[href*="/app/dashboard/my-organization"]')->click();
        $client->waitForVisibility('.content-header-title');
        expect($client->getCurrentURL())->toContain('/app/dashboard/my-organization');
    } finally {
        $client->manage()->window()->setSize(new WebDriverDimension(1440, 900));
    }
});

it('closes the EasyAdmin mobile drawer when crossing to the desktop viewport', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');
    $client->manage()->window()->setSize(new WebDriverDimension(820, 844));

    try {
        $client->request('GET', '/app/dashboard');
        $client->waitForVisibility('.ooo-mobile-toggle');
        $client->getCrawler()->filter('.ooo-mobile-toggle')->click();
        $client->waitForVisibility('.ea-sidebar-backdrop');

        $client->manage()->window()->setSize(new WebDriverDimension(1440, 900));
        $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
            "return !document.querySelector('.ea-sidebar-backdrop') && document.querySelector('#navigation-toggler').getAttribute('aria-expanded') === 'false';"
        ));

        $client->getCrawler()->filter('.ooo-sidebar-item[href*="/app/dashboard/my-organization"]')->click();
        $client->waitForVisibility('.content-header-title');
        expect($client->getCurrentURL())->toContain('/app/dashboard/my-organization');
    } finally {
        $client->manage()->window()->setSize(new WebDriverDimension(1440, 900));
    }
});
