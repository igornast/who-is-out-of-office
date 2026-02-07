# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is "Who's Out of Office" - an online staff leave planner built with **Symfony 7.2** and **PHP 8.4**. The application manages employee leave requests, public holidays, team calendars, and integrates with Slack for notifications. It follows a modular architecture with clean separation of concerns using Facades, DTOs, and Command/Query patterns.

### Application Structure

- **Application code location**: All source code lives in the `/app` directory
- **Application URL prefix**: The application is accessible via `/app` URLs (e.g., `http://localhost/app`)
- **EasyAdmin Bundle**: The application uses **EasyAdmin Bundle v4.25.1** as its primary UI framework
  - EasyAdmin is used throughout the entire application (not just for admin areas)
  - It provides easier rendering, CRUD operations, and reusable components
  - Main dashboard route: `/app/dashboard`

## Development Environment

### Docker Setup

The project runs in Docker with three services:
- **nginx** (port 80): Web server
- **php**: PHP-FPM with Xdebug support
- **db**: MySQL 8.4 (port 3306)

Start services:
```bash
docker-compose up -d
```

Access the PHP container to run commands:
```bash
docker exec -it app_ooo_php bash
```

### Database Credentials

- Database: `ooo_db`
- User: `mysql`
- Password: `mysql`

Default admin account (development only):
- Email: `admin@ooo.com`
- Password: `admin`

## Common Commands

All commands should be run from the `app/` directory or from within the PHP container.

### Testing

```bash
# Run all tests (CS, PHPStan, PHPUnit)
composer test

# Run unit tests with coverage (requires 90% minimum)
composer test:phpunit:u

# Run code style check
composer test:cs

# Fix code style issues
composer test:cs:fix

# Run PHPStan (Level 8)
composer test:stan

# Run CI test suite
composer test-ci
```

### Running Individual Tests

```bash
# Run a specific test file
./vendor/bin/pest tests/Unit/Path/To/YourTest.php

# Run tests with coverage
./vendor/bin/pest --coverage
```

### Database Management

```bash
# Reset test database
composer reset-test-db

# Run migrations
php bin/console doctrine:migrations:migrate

# Update schema (development)
php bin/console doctrine:schema:update --force

# Load fixtures
php bin/console doctrine:fixtures:load
```

### Public Holidays

Import public holidays for a country and year:
```bash
php bin/console app:holiday:import NG Nigeria 2025
```

### Slack Integration

Run weekly digest (typically scheduled for Mondays at 8am):
```bash
php bin/console slack:weekly_digest
```

### Asset Management

The project uses **Symfony AssetMapper** and **Symfony UX** for frontend assets.

```bash
# Compile assets for production
php bin/console asset-map:compile

# Install importmap packages
php bin/console importmap:install
```

**Important**: Assets live in `assets/` and `importmap.php`. Never edit files in `public/assets/` - they are auto-generated.

## Architecture

### Directory Structure

```
app/src/
├── DataFixtures/          # Doctrine fixtures for development/testing
├── Infrastructure/        # External integrations and persistence
│   ├── DataNager/        # Public holiday API integration
│   ├── Doctrine/         # Entities, Repositories, XML Mappings
│   ├── Ical/             # iCalendar export functionality
│   ├── Slack/            # Slack notification and webhook handling
│   └── Traits/           # Reusable traits (e.g., TimestampableTrait)
├── Module/               # Business logic organized by domain
│   ├── Admin/           # Admin panel (EasyAdmin controllers, forms, templates)
│   ├── Holiday/         # Public holiday management
│   ├── LeaveRequest/    # Leave request business logic
│   └── User/            # User management and profiles
└── Shared/              # Cross-cutting concerns
    ├── DTO/             # Data Transfer Objects
    ├── Enum/            # Enums (LeaveRequestStatusEnum, RoleEnum)
    ├── Facade/          # Facade interfaces for modules
    └── Service/         # Shared services (RoleTranslator, etc.)
```

### Key Architectural Patterns

#### 1. Facade Pattern

Each module exposes a `Facade` that provides a high-level API. Facades implement interfaces defined in `Shared/Facade/`:

- `LeaveRequestFacade` → `LeaveRequestFacadeInterface`
- `UserFacade` → `UserFacadeInterface`
- `HolidayFacade` → `HolidayFacadeInterface`
- `SlackFacade` → `SlackFacadeInterface`

Controllers and services interact with modules exclusively through these facades.

#### 2. Command/Query Separation

Business logic is organized using Command and Query handlers in each module's `UseCase/` directory:

- **Commands**: Modify state (e.g., `SaveLeaveRequestCommandHandler`, `UpdateLeaveRequestCommandHandler`)
- **Queries**: Read state (e.g., `GetLeaveRequestsForUserQueryHandler`, `CalculateWorkDaysQueryHandler`)

**Adding a new feature — full wiring flow:**

1. **Repository layer** — Add method to `UserRepositoryInterface` + implement in `UserRepository`
2. **Handler** — Create `UseCase/Command/` or `UseCase/Query/` handler that injects the repository interface
3. **Facade interface** — Add method to `Shared/Facade/[Module]FacadeInterface`
4. **Facade implementation** — Inject handler in constructor, delegate to `$this->handler->handle()`
5. **Consumer** — Controller, console command, or event listener calls the facade method

```
Controller/Command → FacadeInterface → Facade → Handler → RepositoryInterface → Repository
```

All dependencies are injected via constructor and autowired. Facades are the **only** entry point into a module from outside.

#### 3. Data Transfer Objects (DTOs)

All data passed between layers uses DTOs from `Shared/DTO/`:

- `LeaveRequestDTO`
- `UserDTO`
- `PublicHolidayDTO`
- `SaveLeaveRequestCommand`
- `CalculateWorkdaysQuery`

#### 4. Doctrine with XML Mapping

Entities are in `Infrastructure/Doctrine/Entity/` and mapped using XML files in `Infrastructure/Doctrine/Mapping/`. This keeps entities as pure data objects without annotations.

#### 5. Event-Driven Architecture

The application uses Symfony events for cross-cutting concerns:

- `LeaveRequestListener` (Slack notifications when leave requests change)
- Event subscribers in `Module/Admin/EventSubscriber/`

### Module Responsibilities

#### Admin Module
- EasyAdmin-based admin panel
- Dashboard with team overview, birthdays, pending requests
- CRUD controllers for Users, Leave Requests, Leave Types
- User profile settings and calendar views

#### LeaveRequest Module
- Core leave request business logic
- Workday calculation (excluding weekends and public holidays)
- Leave request status management (Pending, Approved, Rejected)
- Integration with public holiday calendars

#### User Module
- User management and authentication
- Team member relationships
- User invitation system
- Profile customization (working days, birthday, Slack integration)

#### Holiday Module
- Public holiday calendar management
- Integration with Date Nager API for importing holidays
- Country-specific holiday calendars

### Infrastructure Layer

#### Slack Integration
- **Approval workflow**: Managers can approve/reject leave requests via Slack buttons
- **Weekly digest**: Scheduled summary of absences and birthdays
- **Private DMs**: User-specific notifications when requests are processed
- **Webhook endpoint**: `/api/slack/interactive-endpoint` handles button clicks
- **Verification**: Uses `SLACK_SIGNING_SECRET` to verify incoming requests

Environment variables required:
```
SLACK_DSN=
SLACK_SIGNING_SECRET=
SLACK_AR_APPROVE_CHANNEL_ID=
SLACK_AR_HR_DIGEST_CHANNEL_ID=
```

#### iCal Export
- Leave requests can be exported as iCalendar feeds
- Secured with hash-based verification
- Accessible via `/api/calendar/{userId}/{hash}.ics`

#### Date Nager Integration
- External API for fetching public holidays by country
- Integrated through `DateNagerFacade` and `DateNagerClient`

## Testing Standards

- **PHPStan**: Level 8 enforcement
- **Code Coverage**: Minimum 90% for unit tests
- **Test Framework**: Pest (PHPUnit wrapper)
- **Fixtures**: Nelmio Alice for test data generation

Tests are organized in `tests/`:
```
tests/
├── Unit/                 # Unit tests
│   ├── Infrastructure/
│   └── Module/
└── _fixtures/            # Test fixtures (DTOs, builders)
```

## Code Quality

- **PHP-CS-Fixer**: Enforces consistent code style
- **PHPStan**: Static analysis at level 8
- DTOs are excluded from PHPStan checks (see `phpstan.neon`)
- All commands should run successfully before committing

### Code Documentation Standards

- **No comments**: Code should be self-documenting through clear naming and structure
- Use descriptive variable names, method names, and class names that explain intent
- Prefer extracting complex logic into well-named private methods over adding comments
- Only use comments when absolutely necessary to explain "why" (not "what") in exceptional cases
- PHPDoc blocks are acceptable for type hints where PHP's type system is insufficient
- **Use sprintf for string formatting**: Prefer `sprintf()` over concatenation for better readability

## Configuration

- **Services**: `app/config/services.yaml` - Uses autowiring by default
- **Doctrine**: XML mappings in `src/Infrastructure/Doctrine/Mapping/`
- **Environment**: `.env.local` for local overrides
- **Assets**: Configured via `importmap.php` and `config/packages/asset_mapper.yaml`

## Critical Patterns and Best Practices

### Testing with Dynamic Dates

**CRITICAL**: Never hardcode year values or date calculations in test assertions that will fail over time.

**Bad Practice:**
```php
// This will fail next year
->and($blocks[8]['text']['text'])->toContain('(10 years)')
```

**Good Practice:**
```php
// Calculate years dynamically
$workYears = new DateTimeImmutable()->diff($user3->contractStartedAt)->y;

// Use calculated value in assertions
->withArgs(function (ChatMessage $message) use ($workYears) {
    // ...
    ->and($blocks[8]['text']['text'])->toContain(sprintf('%s years', $workYears))
}
```

For relative dates in fixtures:
```php
// Guarantee a specific future date relative to "now"
'contractStartedAt' => new DateTimeImmutable('2015-04-22'), // Static date
// Better: Dynamic date that's always X days/years from now
'contractStartedAt' => (new DateTime('+5 days'))->modify('-5 years') // Always 5 years ago, anniversary in 5 days
```

### Recurring User Events Pattern (Birthdays, Work Anniversaries)

When implementing recurring user events (birthdays, work anniversaries, etc.), follow this consistent pattern:

**1. Repository Layer**: SQL with CASE statement to calculate next occurrence
```sql
CASE
    WHEN DATE_FORMAT(contract_started_at, '%m-%d') >= DATE_FORMAT(CURRENT_DATE(), '%m-%d')
        THEN STR_TO_DATE(CONCAT(YEAR(CURRENT_DATE()), '-', DATE_FORMAT(contract_started_at, '%m-%d')), '%Y-%m-%d')
    ELSE STR_TO_DATE(CONCAT(YEAR(CURRENT_DATE())+1, '-', DATE_FORMAT(contract_started_at, '%m-%d')), '%Y-%m-%d')
END BETWEEN :start AND :end
```

**2. Query Handlers**: Two handlers per feature
- `GetUsersWithIncoming[Event]QueryHandler` - Default 20-day range for dashboard
- `GetUsersWithIncoming[Event]ForDatesQueryHandler` - Custom date range for Slack digest

**3. Facade Methods**: Expose through UserFacade
```php
public function getUsersWithIncoming[Event](): array;
public function getUsersWithIncoming[Event]ForDates(\DateTimeImmutable $start, \DateTimeImmutable $end): array;
```

**4. Dashboard Integration**:
- Pass data from controller: `'users_with_[event]' => $this->userFacade->getUsersWithIncoming[Event]()`
- Create separate template partial: `_upcoming_[event].html.twig`
- Follow same structure as existing sections (panel, avatar, date display)

**5. Slack Integration**:
- Fetch data in `handle()`: `$users = $this->userFacade->getUsersWithIncoming[Event]ForDates($monday, $sunday)`
- Create `generate[Event]Section()` method returning Slack blocks
- Include in empty state condition check
- Add to blocks array in options

### Fixture Design for Tests

**Principles**:
1. **Guarantee conditions**: Design fixtures to always meet test requirements
2. **Use relative dates**: Ensure fixtures remain valid over time
3. **Explicit data**: Make test data obvious and readable

**Example from `fixtures.yaml`**:
```yaml
user_1:
    contractStartedAt: '<(new \DateTimeImmutable((new DateTime("+5 days"))->modify("-5 years")->format("Y-m-d H:i:s")))>'
```
This guarantees user_1 always has a work anniversary exactly 5 days from "now", making tests predictable.

**Example from test fixtures**:
```php
// UserDTOFixture.php
'contractStartedAt' => \DateTimeImmutable::createFromMutable($faker->dateTimeThisDecade()),
'hasCelebrateWorkAnniversary' => $faker->boolean(),
```

### Slack Block Kit Structure

Slack notifications follow a consistent block structure:

```php
[
    // Header section
    ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => '✨ Weekly digest']],

    // Context section (metadata)
    ['type' => 'context', 'elements' => [...]],

    // Content sections (repeating pattern)
    ['type' => 'divider'],
    ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '📆 | *Section Title*']],
    ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $contentText]],
]
```

**Key patterns**:
- Each major section starts with a divider
- Section headers use emoji + bold title with mrkdwn format
- User names are always bold: `*FirstName LastName*`
- Use bullet character `‣` for list items with indentation
- Calculate years/age dynamically: `$currentYear - $startYear`
- Handle singular/plural: `1 === $years ? 'year' : 'years'`

### Template Architecture

Templates use **custom rendering** (not Symfony's default `form(form)`) — each field is rendered individually with `form_row()`, `form_widget()`, `form_label()`, and `form_help()`.

**Twig namespaces** (configured in `config/packages/twig.yaml`):
- `@AppAdmin` → `src/Module/Admin/template/`
- `@AppUser` → `src/Module/User/template/`

**Template structure:**
```
Module/Admin/template/
├── layout.html.twig                    # Base layout, extends @EasyAdmin/layout.html.twig
├── dashboard.html.twig                 # Main dashboard, includes partials
├── dashboard/
│   ├── _employ_info.html.twig          # User info + balance sidebar
│   ├── _slack_integration_info.html.twig
│   ├── _my_team.html.twig             # Team avatars
│   ├── _upcoming_absences.html.twig
│   ├── _upcoming_birthdays.html.twig
│   ├── _upcoming_work_anniversaries.html.twig
│   └── component/_user_avatar.html.twig  # Reusable avatar macro
├── user/
│   └── profile_edit.html.twig          # User profile form (custom rendering)
├── settings/
│   └── edit.html.twig                  # App settings form
├── leave_request/
│   └── new.html.twig                   # New leave request page
├── component/
│   └── LeaveRequestForm.html.twig      # Symfony UX TwigComponent
└── calendar_view.html.twig
```

**Form rendering pattern** (used in `profile_edit.html.twig` and `settings/edit.html.twig`):
```twig
{# Text/date fields #}
<div class="field-text form-group">
    <div class="form-widget">
        {{ form_row(form.fieldName, {'attr': {'class': 'form-control'}}) }}
    </div>
</div>

{# Disabled/readonly date field with label + help #}
<div class="field-choice form-group">
    <div class="form-widget">
        {{ form_label(form.fieldName) }}
        {{ form_widget(form.fieldName, {'attr': {'class': 'form-control'}}) }}
    </div>
    <div class="form-text">{{ form_help(form.fieldName) }}</div>
</div>

{# Checkbox field #}
<div class="field-checkbox form-group">
    <div class="checkbox-large">
        {{ form_widget(form.fieldName) }}
        {{ form_label(form.fieldName) }}
    </div>
    <div class="form-text">{{ form_help(form.fieldName) }}</div>
</div>
```

**Reusable avatar macro** (`_user_avatar.html.twig`):
```twig
{% import "@AppAdmin/dashboard/component/_user_avatar.html.twig" as avatar %}
{{ avatar.userDtoAvatar(userDto) }}
```

### Dashboard Template Patterns

Dashboard sections follow consistent patterns:

```twig
<div class="panel panel-primary">
    <div class="panel-heading border-bottom mb-4 d-flex align-items-center pb-3">
        <div class="me-3">
            {# Bootstrap icon SVG #}
        </div>
        <h6 class="panel-title text-body-emphasis mb-0">
            {{ 'dashboard.[section].title'|trans(domain: 'admin') }}
        </h6>
    </div>
    <div class="panel-body card">
        <div class="card-body d-flex h-25">
            {% for userDto in users %}
                <div>
                    {{ avatar.userDtoAvatar(userDto) }}
                    <div class="w-100 pe-3 text-center text-secondary">
                        {{ userDto.[dateField]|date("m.d") }}
                        {# Additional info like years #}
                    </div>
                </div>
            {% endfor %}

            {% if users is empty %}
                <div class="alert alert-secondary w-100 text-center">
                    {{ 'dashboard.[section].no_upcoming'|trans(domain: 'admin') }}
                </div>
            {% endif %}
        </div>
    </div>
</div>
```

**Icons used**:
- Birthdays: `bi-cake2-fill`
- Work Anniversaries: `bi-trophy-fill`
- Leave Requests: `bi-calendar-event-fill`

### Scheduled Task Pattern

All scheduled commands use the same style: do **not** extend `Command`, use `__invoke()`, no `parent::__construct()`. The scheduling attribute varies:

- **AsCronTask**: Cron expression (e.g., `'0 0 * * *'`). Example: `WeeklyDigestCommand`, `AbsenceBalanceResetCommand`.
- **AsPeriodicTask**: Frequency string (e.g., `'1 minute'`). Example: `LeaveRequestAutoApproveCommand`.

```php
#[
    AsCommand(name: 'example:command', description: 'Example scheduled command'),
    AsCronTask(expression: '0 0 * * *'),
]
class ExampleCommand
{
    public function __construct(private readonly SomeFacadeInterface $facade)
    {
    }

    public function __invoke(): int
    {
        $this->facade->doSomething();

        return Command::SUCCESS;
    }
}
```

### Console Command Location Convention

Commands live within their domain module, not centralized:
- `Infrastructure/Slack/Command/` — Slack-related commands
- `Module/LeaveRequest/Command/` — Leave request commands
- `Module/User/Command/` — User commands

### UserRepository::update() Manual Mapping

When adding new fields to the User entity/DTO, you **must** also add explicit mapping in `UserRepository::update()`. It does not auto-map — each field is assigned individually from DTO to entity.

### UserDTO::fromArray() Null Safety

Raw SQL queries use `UserDTO::fromArray()`. When adding fields, handle cases where the column may not exist in older data or test fixtures that build arrays manually (use `isset()` check or provide defaults).

## Important Notes

- The application requires PHP 8.4+
- Always run commands from the `app/` directory
- Doctrine entities are in `Infrastructure/` layer, not `Module/`
- Use facades when accessing module functionality from controllers
- DTOs are the contract between layers - modify carefully
- Slack integration requires proper webhook setup in Slack App settings
