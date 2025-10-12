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

## Configuration

- **Services**: `app/config/services.yaml` - Uses autowiring by default
- **Doctrine**: XML mappings in `src/Infrastructure/Doctrine/Mapping/`
- **Environment**: `.env.local` for local overrides
- **Assets**: Configured via `importmap.php` and `config/packages/asset_mapper.yaml`

## Important Notes

- The application requires PHP 8.4+
- Always run commands from the `app/` directory
- Doctrine entities are in `Infrastructure/` layer, not `Module/`
- Use facades when accessing module functionality from controllers
- DTOs are the contract between layers - modify carefully
- Slack integration requires proper webhook setup in Slack App settings
