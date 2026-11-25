<?php

declare(strict_types=1);

use App\Module\Admin\Controller\AppSettingsController;
use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class SaveOutcomeTestableAppSettingsController extends AppSettingsController
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

it('flashes success and redirects when the settings write succeeds', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->expects('getAllSettings')->andReturn(new AppSettingsDTO(
        autoApprove: false,
        autoApproveDelay: 0,
        defaultAnnualAllowance: 20,
        minNoticeDays: 0,
        maxConsecutiveDays: 0,
        skipWeekendHolidays: false,
        slackStatusSyncEnabled: false,
        organizationName: 'Old',
    ));
    $facade->expects('updateAllSettings')->once();

    $form = mock(FormInterface::class);
    $form->allows('handleRequest');
    $form->allows('isSubmitted')->andReturnTrue();
    $form->allows('isValid')->andReturnTrue();

    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->never();
    $translator = mock(TranslatorInterface::class);
    $translator->expects('trans')->never();

    $controller = new SaveOutcomeTestableAppSettingsController(
        settingFilename: '/tmp/app_setting.yaml',
        appSettingsFacade: $facade,
        logger: $logger,
        translator: $translator,
    );
    $controller->form = $form;

    $response = $controller(new Request());

    expect($response->getStatusCode())->toBe(302)
        ->and($controller->flashes)->toBe(['success' => 'Settings updated successfully!']);
});
