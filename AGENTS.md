# Repository Guidelines

## Project Structure & Module Organization

This Symfony 7.4/PHP 8.5 application keeps all app code under `app/`. Domain code lives in `app/src/Module/` (`Admin`, `Feed`, `Holiday`, `LeaveRequest`, `Settings`, `User`). External integrations and persistence live in `app/src/Infrastructure/`, including Doctrine entities and XML mappings. Cross-cutting DTOs, enums, facades, and shared handlers live in `app/src/Shared/`. Tests are split into `app/tests/Unit`, `app/tests/Functional`, and `app/tests/Architecture`; fixtures and sample payloads are under `app/tests/_fixtures` and `app/tests/_sample-data`. Frontend assets live in `app/assets`; do not edit generated files in `app/public/assets`.

## Build, Test, and Development Commands

Use Docker and `just` from the repository root:

- `just start` starts nginx, PHP-FPM, MySQL, and MailPit.
- `just stop` stops the stack.
- `just build` rebuilds containers and starts the stack.
- `just shell` opens a shell in the PHP container.

Run app commands through the container:

- `docker exec app_ooo_php just test` runs CS, PHPStan, architecture, unit, and functional tests.
- `docker exec app_ooo_php just test-unit` runs unit tests with coverage.
- `docker exec app_ooo_php just test-functional` resets the test DB and runs functional tests.
- `docker exec app_ooo_php just pest-filter "Name"` runs matching Pest tests.
- `docker exec app_ooo_php just cs` fixes PHP-CS-Fixer issues.
- `docker exec app_ooo_php just stan` runs PHPStan level 8.

## Coding Style & Naming Conventions

Follow `CLAUDE.md` for architecture. Module boundaries are enforced: consumers call `Shared/Facade/*Interface`, facades delegate to command/query handlers, and handlers use repository interfaces. Do not import across modules directly. Doctrine entities use XML mappings, not annotations. Use constructor injection, DTOs for layer boundaries, `sprintf()` for formatted strings, and Symfony translations for user-facing text. PHP-CS-Fixer handles formatting; local style includes no spaces around `.` concatenation and no `final` classes.

## Testing Guidelines

Tests use Pest/PHPUnit. Name tests after the subject, for example `LeaveRequestFacadeTest` or `CalendarExportControllerTest`, and place them in the matching Unit, Functional, or Architecture tree. Unit coverage has a 90% minimum. Reset the test database before standalone functional tests with `docker exec app_ooo_php just db-reset-test`, or use the provided functional/full test targets.

## Commit & Pull Request Guidelines

Git history follows concise conventional-style subjects such as `feat: add auto approve time forecast`, `fix: calendar customization modal checkboxes style`, and `docs(changelog): update for v0.3.0`. Keep commits focused and use `feat:`, `fix:`, `docs:`, or `tests:` where appropriate. Pull requests should describe what changed and why, link related issues, include screenshots for UI changes, and confirm `docker exec app_ooo_php just test` passes.
