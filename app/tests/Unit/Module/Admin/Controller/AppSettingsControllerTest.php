<?php

declare(strict_types=1);

use App\Module\Admin\Controller\AppSettingsController;
use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Exception\AppSettingsWriteException;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class TestableAppSettingsController extends AppSettingsController
{
    /** @var array<string, mixed> */
    public array $flashes = [];

    public FormInterface $form;

    protected function createForm(string $type, mixed $data = null, array $options = []): FormInterface
    {
        return $this->form;
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->flashes[$type] = $message;
    }

    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return new RedirectResponse('/', $status);
    }

    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        return new Response('', 200);
    }
}

it('reports an atomic settings write failure instead of flashing success', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $settings = new AppSettingsDTO(
        autoApprove: false,
        autoApproveDelay: 0,
        defaultAnnualAllowance: 20,
        minNoticeDays: 0,
        maxConsecutiveDays: 0,
        skipWeekendHolidays: false,
        slackStatusSyncEnabled: false,
        organizationName: 'Old',
    );
    $facade->expects('getAllSettings')->andReturn($settings);
    $facade->expects('updateAllSettings')->andThrow(new AppSettingsWriteException('broken'));

    $form = mock(FormInterface::class);
    $form->allows('handleRequest');
    $form->allows('isSubmitted')->andReturnTrue();
    $form->allows('isValid')->andReturnTrue();
    $form->allows('createView')->andReturn(new FormView());

    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->once()->with(
        '[SETTINGS][SAVE]: Failed to write app settings.',
        ['exception' => 'broken'],
    );
    $translator = mock(TranslatorInterface::class);
    $translator->expects('trans')->once()->withArgs(
        fn (string $id, array $parameters, ?string $domain): bool => 'crud.app_settings.flash.save_error' === $id
            && [] === $parameters
            && 'admin' === $domain,
    )->andReturn('Could not save settings.');

    $controller = new TestableAppSettingsController(
        settingFilename: '/tmp/app_setting.yaml',
        appSettingsFacade: $facade,
        logger: $logger,
        translator: $translator,
    );
    $controller->form = $form;

    $response = $controller(new Request());

    expect($response->getStatusCode())->toBe(200)
        ->and($controller->flashes)->toBe(['error' => 'Could not save settings.']);
});
