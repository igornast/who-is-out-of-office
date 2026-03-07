# Settings Module

The Settings module provides a YAML-based configuration system that allows administrators to configure application behavior without code changes.

## Architecture

The Settings module follows the application's standard architecture patterns:

```
Settings/
├── Config/
│   └── app_setting.yaml          # YAML configuration file
├── Exception/
│   └── InvalidAppSettingTypeException.php
├── UseCase/
│   ├── Command/
│   │   └── UpdateAppSettingsValueCommandHandler.php
│   └── Query/
│       ├── GetAllAppSettingsQueryHandler.php
│       └── GetAppSettingsValueQueryHandler.php
├── AppSettingsFacade.php         # Facade implementation
└── README.md                      # This file
```

**Shared components:**
- `Shared/Enum/AppSettingsEnum.php` - Setting key definitions
- `Shared/DTO/Settings/AppSettingsDTO.php` - Data transfer object
- `Shared/Facade/AppSettingsFacadeInterface.php` - Facade interface

**Admin UI:**
- `Module/Admin/Controller/AppSettingsController.php` - Settings controller
- `Module/Admin/Form/AppSettingsFormType.php` - Settings form
- `Module/Admin/template/settings/edit.html.twig` - Settings template

## Available Settings

### Leave Request Settings

| Setting | Type | Description | Default |
|---------|------|-------------|---------|
| `auto_approve` | boolean | Enable/disable automatic approval of leave requests | `false` |
| `auto_approve_delay` | integer | Delay in seconds before automatically approving leave requests | `6900` |

## Managing Settings

### Via Web Interface

Administrators can manage settings through the UI:

1. Log in with an admin account
2. Navigate to **App Settings** in the sidebar menu
3. Update settings and click **Save Changes**

### Via Code

Access settings through the `AppSettingsFacadeInterface`:

```php
use App\Shared\Facade\AppSettingsFacadeInterface;

public function __construct(
    private readonly AppSettingsFacadeInterface $appSettingsFacade,
) {}

public function someMethod(): void
{
    // Get individual settings
    $autoApprove = $this->appSettingsFacade->isAutoApprove();
    $delay = $this->appSettingsFacade->autoApproveDelay();

    // Get all settings as DTO
    $settings = $this->appSettingsFacade->getAllSettings();

    // Update all settings
    $newSettings = new AppSettingsDTO(
        autoApprove: false,
        autoApproveDelay: 3600,
        defaultAnnualAllowance: 25,
        minNoticeDays: 1,
        maxConsecutiveDays: 0,
    );
    $this->appSettingsFacade->updateAllSettings($newSettings);
}
```

## Environment Configuration

The location of the settings file is controlled by the `APP_SETTINGS_FILE` environment variable:

```dotenv
###> app/settings-module ###
APP_SETTINGS_FILE='%kernel.project_dir%/src/Module/Settings/Config/app_setting.yaml'
###< app/settings-module ###
```

This allows you to use different settings files for different environments (dev, staging, production)


## Adding New Settings

Follow these steps to add a new setting:

### 1. Define the Setting Key

Add a new case to `AppSettingsEnum`:

```php
// app/src/Shared/Enum/AppSettingsEnum.php

enum AppSettingsEnum: string
{
    case AUTO_APPROVE = 'leave_request.auto_approve';
    case AUTO_APPROVE_DELAY = 'leave_request.auto_approve_delay';
    case YOUR_NEW_SETTING = 'section_name.setting_name'; // Add here
}
```

The enum value uses dot notation for nested YAML structure:
- `section_name.setting_name` → `section_name: { setting_name: value }`
- `a.b.c` → `a: { b: { c: value } }`

### 2. Update the DTO

Add the property to `AppSettingsDTO`:

```php
// app/src/Shared/DTO/Settings/AppSettingsDTO.php

public function __construct(
    #[Assert\NotNull]
    public bool $autoApprove,
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public int $autoApproveDelay,
    public string $yourNewSetting, // Add property with validation
) {}
```

Update `fromArray()` method:

```php
public static function fromArray(array $data): self
{
    return new self(
        autoApprove: self::getNestedValue($data, AppSettingsEnum::AUTO_APPROVE),
        autoApproveDelay: self::getNestedValue($data, AppSettingsEnum::AUTO_APPROVE_DELAY),
        yourNewSetting: self::getNestedValue($data, AppSettingsEnum::YOUR_NEW_SETTING), // Add here
    );
}
```

Update `toArray()` method:

```php
public function toArray(): array
{
    $result = [];
    self::setNestedValue($result, AppSettingsEnum::AUTO_APPROVE, $this->autoApprove);
    self::setNestedValue($result, AppSettingsEnum::AUTO_APPROVE_DELAY, $this->autoApproveDelay);
    self::setNestedValue($result, AppSettingsEnum::YOUR_NEW_SETTING, $this->yourNewSetting); // Add here

    return $result;
}
```

### 3. Add Facade Method (Optional)

If you want a dedicated method for your setting, add it to the interface and implementation:

```php
// app/src/Shared/Facade/AppSettingsFacadeInterface.php

interface AppSettingsFacadeInterface
{
    public function isAutoApprove(): bool;
    public function autoApproveDelay(): int;
    public function yourNewSetting(): string; // Add here
    // ...
}
```

```php
// app/src/Module/Settings/AppSettingsFacade.php

public function yourNewSetting(): string
{
    $value = $this->appSettingValueHandler->handle(AppSettingsEnum::YOUR_NEW_SETTING);

    if (!is_string($value)) {
        throw new InvalidAppSettingTypeException(expected: 'string', settingsEnum: AppSettingsEnum::YOUR_NEW_SETTING);
    }

    return $value;
}
```

### 4. Add Form Field

Update the settings form:

```php
// app/src/Module/Admin/Form/AppSettingsFormType.php

public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('autoApprove', CheckboxType::class, [...])
        ->add('autoApproveDelay', IntegerType::class, [...])
        ->add('yourNewSetting', TextType::class, [
            'label' => 'crud.app_settings.field.your_new_setting',
            'help' => 'crud.app_settings.field.your_new_setting_help',
            'required' => true,
        ]);
}
```

### 5. Add Translations

Add labels to the translation file:

```yaml
# app/translations/admin.en.yaml

crud:
    app_settings:
        field:
            your_new_setting: Your New Setting
            your_new_setting_help: Description of what this setting does
```

### 6. Update Template (if needed)

If you want to organize settings into sections, update the template:

```twig
{# app/src/Module/Admin/template/settings/edit.html.twig #}

<h5>{{ 'crud.app_settings.panel.new_section'|trans(domain='admin') }}</h5>

<div class="field-text form-group mt-3">
    <div class="form-widget">
        {{ form_row(form.yourNewSetting, {'attr': {'class': 'form-control'}}) }}
    </div>
</div>
```

### 7. Update YAML File

Add the default value to the settings file:

```yaml
# app/src/Module/Settings/Config/app_setting.yaml

leave_request:
    auto_approve: false
    auto_approve_delay: 6900

section_name:
    setting_name: default_value
```

## Type Safety

The module enforces type safety at multiple levels:

1. **Enum** - Setting keys are type-safe enum cases
2. **DTO** - Properties have type hints and validation constraints
3. **Facade** - Individual methods return typed values and throw exceptions for type mismatches
4. **Form** - Symfony form validation ensures correct types from user input

Example of type checking in facade:

```php
public function autoApproveDelay(): int
{
    $value = $this->appSettingValueHandler->handle(AppSettingsEnum::AUTO_APPROVE_DELAY);

    if (!is_int($value)) {
        throw new InvalidAppSettingTypeException(
            expected: 'int',
            settingsEnum: AppSettingsEnum::AUTO_APPROVE_DELAY
        );
    }

    return $value;
}
```

## Best Practices

1. **Always use the enum** - Never hardcode setting keys as strings
2. **Add validation** - Use Symfony constraints on DTO properties
3. **Document defaults** - Keep the YAML file updated with sensible defaults
4. **Type check** - Always validate types when adding facade methods
5. **Translate everything** - Use translation keys for all UI text
6. **Test your settings** - Write unit tests for new settings logic

## Security Considerations

- Settings are only editable by users with `ROLE_ADMIN`
- The YAML file should not be web-accessible
