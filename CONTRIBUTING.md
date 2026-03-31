# Contributing to Who's Out of Office

Thank you for your interest in contributing! This guide covers how to get set up, run tests, and submit changes.

## Getting Started

### Prerequisites

- Docker and Docker Compose
- Git
- [`just`](https://github.com/casey/just) (command runner used throughout the project)

### Local Setup

```bash
git clone https://github.com/igornast/who-is-out-of-office.git
cd who-is-out-of-office
just start
```

The PHP container handles everything on first boot: installs dependencies, waits for the database, runs migrations, and loads dev fixtures.

The app is available at `http://localhost/app/dashboard`.

Default dev credentials:
- Email: `admin@whoisooo.app`
- Password: `123`

## Running Tests

The project uses [Pest](https://pestphp.com/) and enforces PHPStan level 8 with a minimum 90% unit test coverage.

```bash
# Full test suite (CS, PHPStan, Architecture, Unit, Functional)
docker exec app_ooo_php just test

# Unit tests only
docker exec app_ooo_php just test-unit

# Functional tests only
docker exec app_ooo_php just test-functional

# Tests matching a name filter
docker exec app_ooo_php just pest-filter "SomeTestName"

# Fix code style
docker exec app_ooo_php just cs

# PHPStan static analysis
docker exec app_ooo_php just stan
```

All tests must pass before submitting a pull request.

## Code Style

The project uses [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer). Run `just cs` to auto-fix style issues before committing.

Key rules:
- No spaces around `.` concatenation: `$a.$b` not `$a . $b`
- No `final` on classes (mocking in tests takes priority)
- No inline comments — code should be self-documenting
- Use `sprintf()` for string formatting, not concatenation

## Architecture

The codebase follows a modular architecture with strict layer boundaries:

```
Controller → FacadeInterface → Facade → Handler → RepositoryInterface → Repository
```

- Modules communicate only through `Shared/Facade/` interfaces — never import across module boundaries directly
- Business logic lives in `UseCase/Command/` and `UseCase/Query/` handlers
- Doctrine entities use XML mapping (no annotations)
- All user-facing strings use Symfony translations — no hardcoded text in templates

See [`CLAUDE.md`](CLAUDE.md) for the full architecture guide.

## Submitting Changes

1. Fork the repository and create a branch from `main`
2. Make your changes with tests
3. Ensure `docker exec app_ooo_php just test` passes fully
4. Open a pull request against `main` with a clear description of what and why

For larger changes (new modules, architectural changes), open an issue first to discuss the approach before investing time in implementation.

## Reporting Bugs

Open a [GitHub Issue](https://github.com/igornast/who-is-out-of-office/issues) with:
- Steps to reproduce
- Expected vs actual behavior
- PHP/Symfony version and environment details

For security vulnerabilities, see [SECURITY.md](SECURITY.md).
