<?php

declare(strict_types=1);

use App\Module\Admin\Controller\IntegrationSettingsController;
use App\Module\Admin\DTO\IntegrationStatusDTO;
use App\Shared\DTO\Slack\SlackAdminTokenDTO;
use App\Shared\Facade\SlackFacadeInterface;
use Psr\Container\ContainerInterface;
use Twig\Environment;

beforeEach(function (): void {
    $this->capturedParams = null;

    $twig = mock(Environment::class);
    $twig->allows('render')->andReturnUsing(function (string $view, array $params = []) {
        $this->capturedParams = $params;

        return '';
    });

    $container = mock(ContainerInterface::class);
    $container->allows('has')->with('serializer')->andReturn(false);
    $container->allows('has')->andReturn(true);
    $container->allows('get')->with('twig')->andReturn($twig);

    $this->container = $container;

    $this->slackFacade = mock(SlackFacadeInterface::class);
    $this->slackFacade->allows('getAdminToken')->andReturnNull();
});

it('passes active status for slack notifications when all env vars are set', function (): void {
    $controller = new IntegrationSettingsController(
        slackFacade: $this->slackFacade,
        slackDsn: 'slack://token@default',
        slackSigningSecret: 'secret',
        slackApproveChannelId: 'C123',
        slackHrDigestChannelId: 'C456',
        slackClientId: '',
        slackClientSecret: '',
        slackTokenEncryptionKey: '',
        icalSecret: '',
    );
    $controller->setContainer($this->container);

    ($controller)();

    expect($this->capturedParams['slack_notifications'])
        ->toBeInstanceOf(IntegrationStatusDTO::class)
        ->and($this->capturedParams['slack_notifications']->status)
        ->toBe(IntegrationStatusDTO::STATUS_ACTIVE)
        ->and($this->capturedParams['slack_notifications']->missingVars)
        ->toBe([]);
});

it('passes incomplete status for slack notifications when some env vars are missing', function (): void {
    $controller = new IntegrationSettingsController(
        slackFacade: $this->slackFacade,
        slackDsn: 'slack://token@default',
        slackSigningSecret: '',
        slackApproveChannelId: 'C123',
        slackHrDigestChannelId: '',
        slackClientId: '',
        slackClientSecret: '',
        slackTokenEncryptionKey: '',
        icalSecret: '',
    );
    $controller->setContainer($this->container);

    ($controller)();

    expect($this->capturedParams['slack_notifications']->status)
        ->toBe(IntegrationStatusDTO::STATUS_INCOMPLETE)
        ->and($this->capturedParams['slack_notifications']->missingVars)
        ->toBe(['SLACK_SIGNING_SECRET', 'SLACK_AR_HR_DIGEST_CHANNEL_ID']);
});

it('passes disabled status for slack notifications when no env vars are set', function (): void {
    $controller = new IntegrationSettingsController(
        slackFacade: $this->slackFacade,
        slackDsn: '',
        slackSigningSecret: '',
        slackApproveChannelId: '',
        slackHrDigestChannelId: '',
        slackClientId: '',
        slackClientSecret: '',
        slackTokenEncryptionKey: '',
        icalSecret: '',
    );
    $controller->setContainer($this->container);

    ($controller)();

    expect($this->capturedParams['slack_notifications']->status)
        ->toBe(IntegrationStatusDTO::STATUS_DISABLED);
});

it('passes needs_auth status for slack status sync when env vars are set but no token', function (): void {
    $controller = new IntegrationSettingsController(
        slackFacade: $this->slackFacade,
        slackDsn: '',
        slackSigningSecret: '',
        slackApproveChannelId: '',
        slackHrDigestChannelId: '',
        slackClientId: 'client-id',
        slackClientSecret: 'client-secret',
        slackTokenEncryptionKey: 'enc-key',
        icalSecret: '',
    );
    $controller->setContainer($this->container);

    ($controller)();

    expect($this->capturedParams['slack_status_sync'])
        ->toBeInstanceOf(IntegrationStatusDTO::class)
        ->and($this->capturedParams['slack_status_sync']->status)
        ->toBe(IntegrationStatusDTO::STATUS_NEEDS_AUTH);
});

it('passes active status for slack status sync when env vars are set and token exists', function (): void {
    $facade = mock(SlackFacadeInterface::class);
    $facade->allows('getAdminToken')->andReturn(new SlackAdminTokenDTO('id', 'token', 'U123', new DateTimeImmutable()));

    $controller = new IntegrationSettingsController(
        slackFacade: $facade,
        slackDsn: '',
        slackSigningSecret: '',
        slackApproveChannelId: '',
        slackHrDigestChannelId: '',
        slackClientId: 'client-id',
        slackClientSecret: 'client-secret',
        slackTokenEncryptionKey: 'enc-key',
        icalSecret: '',
    );
    $controller->setContainer($this->container);

    ($controller)();

    expect($this->capturedParams['slack_status_sync']->status)
        ->toBe(IntegrationStatusDTO::STATUS_ACTIVE);
});

it('passes incomplete status for slack status sync when some env vars are missing', function (): void {
    $controller = new IntegrationSettingsController(
        slackFacade: $this->slackFacade,
        slackDsn: '',
        slackSigningSecret: '',
        slackApproveChannelId: '',
        slackHrDigestChannelId: '',
        slackClientId: 'client-id',
        slackClientSecret: '',
        slackTokenEncryptionKey: 'enc-key',
        icalSecret: '',
    );
    $controller->setContainer($this->container);

    ($controller)();

    expect($this->capturedParams['slack_status_sync']->status)
        ->toBe(IntegrationStatusDTO::STATUS_INCOMPLETE)
        ->and($this->capturedParams['slack_status_sync']->missingVars)
        ->toBe(['SLACK_CLIENT_SECRET']);
});

it('passes correct ical status based on ICAL_SECRET', function (): void {
    $controller = new IntegrationSettingsController(
        slackFacade: $this->slackFacade,
        slackDsn: '',
        slackSigningSecret: '',
        slackApproveChannelId: '',
        slackHrDigestChannelId: '',
        slackClientId: '',
        slackClientSecret: '',
        slackTokenEncryptionKey: '',
        icalSecret: 'some-secret',
    );
    $controller->setContainer($this->container);

    ($controller)();

    expect($this->capturedParams['ical_enabled'])->toBeTrue()
        ->and($this->capturedParams['date_nager_enabled'])->toBeTrue();
});

it('passes ical disabled when ICAL_SECRET is empty', function (): void {
    $controller = new IntegrationSettingsController(
        slackFacade: $this->slackFacade,
        slackDsn: '',
        slackSigningSecret: '',
        slackApproveChannelId: '',
        slackHrDigestChannelId: '',
        slackClientId: '',
        slackClientSecret: '',
        slackTokenEncryptionKey: '',
        icalSecret: '',
    );
    $controller->setContainer($this->container);

    ($controller)();

    expect($this->capturedParams['ical_enabled'])->toBeFalse();
});
