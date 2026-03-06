# Plan: Admin-Managed Holiday Calendars

## Problem Statement

Holiday calendars can only be imported via CLI command (`app:holiday:import NG Nigeria 2025`).
Admins cannot add, enable/disable, sync, or remove calendars from the admin UI.
The current upsert replaces ALL holidays on re-import (no year isolation).
There is no scheduled sync — holidays go stale silently each year.

## Current Architecture

```
CLI (ImportPublicHolidaysCommand)
  → DateNagerFacade.getHolidaysForCountry(code, year)
    → DateNagerClient.fetchHolidays() → GET /api/v3/PublicHolidays/{year}/{code}
    → returns NagerPublicHolidayDTO[]
  → PublicHolidayCalendarDTO.createFromNager()
  → HolidayFacade.upsertHolidayCalendar()
    → PublicHolidayCalendarRepository.upsertByCountryCode()
      → orphan-removal: clears ALL old holidays, adds new ones
      → DB: holiday_calendar (1) → (N) holiday
```

**Key entities:**
- `HolidayCalendar`: id, countryCode (max 4), countryName (max 100), holidays[], timestamps
- `Holiday`: id, description, date (date_immutable), holidayCalendar (FK), timestamps
- `User`: holidayCalendar (nullable ManyToOne) — one calendar per user

**DateNager API endpoints used:**
- `GET /api/v3/PublicHolidays/{year}/{countryCode}` — holidays for country+year

**DateNager API endpoint available but not used:**
- `GET /api/v3/AvailableCountries` — returns 195 countries as `[{countryCode, name}]`

---

## Implementation Plan

### Step 1 — Add `isActive` and `lastSyncedYear` to HolidayCalendar entity

**Files to change:**
- `Infrastructure/Doctrine/Entity/HolidayCalendar.php` — add `isActive: bool` (default `true`) and `lastSyncedYear: ?int` (nullable)
- `Infrastructure/Doctrine/Mapping/HolidayCalendar.orm.xml` — add field mappings
- Create Doctrine migration

**DTO changes:**
- `Shared/DTO/Holiday/PublicHolidayCalendarDTO` — add `isActive` and `lastSyncedYear` fields
- Update `fromEntity()` and `createFromNager()` factory methods
- Update `PublicHolidayCalendarDTOFixture` with new defaults

**Why `lastSyncedYear`:** Tracks which year was last imported. The auto-sync command uses this to know which calendars need a refresh. When an admin imports 2026 holidays, this gets set to 2026. On Jan 1, the scheduled sync can detect calendars where `lastSyncedYear < currentYear` and sync them.

### Step 2 — Make holiday upsert year-aware

**Problem:** Currently `upsertByCountryCode()` uses orphan-removal which clears ALL holidays when re-importing. Importing 2026 destroys 2025 data.

**Files to change:**
- `Infrastructure/Doctrine/Repository/PublicHolidayCalendarRepository.php`
  - Change `upsertByCountryCode()` to accept an optional `?int $year` parameter
  - When `$year` is provided: DELETE only holidays for that year (by date range Jan 1 – Dec 31) before inserting new ones, instead of relying on orphan-removal
  - When `$year` is null: keep current behaviour (full replace) for backward compatibility
  - Update `lastSyncedYear` on the entity when `$year` is provided
- `PublicHolidayCalendarRepositoryInterface` — update method signature

**Note:** Orphan-removal on the OneToMany mapping stays for cascade-delete of the calendar itself. The year-aware delete is a manual DQL operation before adding new holidays.

### Step 3 — Extend DateNagerClient with available countries endpoint

**Files to create/change:**
- `Infrastructure/DataNager/Http/DateNagerClient.php` — add `fetchAvailableCountries(): array` method
  - Calls `GET /api/v3/AvailableCountries`
  - Returns `array<array{countryCode: string, name: string}>`
- `Shared/DTO/DataNager/NagerAvailableCountryDTO.php` — new DTO with `countryCode: string`, `name: string`, `fromArray()` factory
- `Infrastructure/DataNager/DateNagerFacade.php` — add `getAvailableCountries(): NagerAvailableCountryDTO[]`
- `Shared/Facade/DateNagerInterface.php` — add method to interface

**Tests:**
- Unit test for `DateNagerFacade::getAvailableCountries()` (mock client)
- Fixture: `NagerAvailableCountryDTOFixture`

### Step 4 — Add HolidayFacade methods for calendar management

**New facade methods on `HolidayFacadeInterface`:**

```php
// Get all calendars (for admin list)
public function getAllCalendars(): array;

// Toggle calendar active state
public function toggleCalendarActive(string $calendarId, bool $isActive): void;

// Sync a specific calendar for a given year (re-import from API)
public function syncCalendar(string $countryCode, string $countryName, int $year): void;

// Sync all active calendars for a given year
public function syncAllActiveCalendars(int $year): void;

// Delete a calendar (only if no users assigned)
public function deleteCalendar(string $calendarId): void;
```

**New handlers in `Module/Holiday/UseCase/`:**

| Handler | Type | Description |
|---------|------|-------------|
| `GetAllCalendarsQueryHandler` | Query | Returns all `PublicHolidayCalendarDTO[]` from repository |
| `ToggleCalendarActiveCommandHandler` | Command | Sets `isActive` flag on calendar entity |
| `SyncCalendarCommandHandler` | Command | Fetches holidays from DateNager for given country+year, calls year-aware upsert |
| `SyncAllActiveCalendarsCommandHandler` | Command | Iterates active calendars, calls `SyncCalendarCommandHandler` for each |
| `DeleteCalendarCommandHandler` | Command | Checks no users are assigned, deletes calendar |

**Repository additions:**
- `PublicHolidayCalendarRepository::findAll(): PublicHolidayCalendarDTO[]`
- `PublicHolidayCalendarRepository::updateActive(UuidInterface $id, bool $isActive): void`
- `PublicHolidayCalendarRepository::delete(UuidInterface $id): void`
- `PublicHolidayCalendarRepository::hasAssignedUsers(UuidInterface $id): bool`

### Step 5 — Admin UI: Add Calendar page

**New controller:** `Module/Admin/Controller/HolidayCalendarImportController.php`

**Route:** `/app/settings/public-holidays/import` (ROLE_ADMIN)

**Form (`HolidayCalendarImportFormType`):**
- **Country** — ChoiceType dropdown populated from `DateNagerFacade::getAvailableCountries()`, excluding countries that already have a calendar
- **Year** — IntegerType, default current year, min/max constraints (current year -1 to current year +1)

**On submit:**
1. Call `HolidayFacade::syncCalendar(countryCode, countryName, year)`
2. Flash success message
3. Redirect to holiday calendar index

**Template:** Follow existing settings page patterns (`@AppAdmin/settings/` style).

### Step 6 — Admin UI: Calendar list actions

**Changes to `HolidayCalendarCrudController`:**

Enable these actions on the index/detail pages:

1. **Enable/Disable toggle** — Custom action on INDEX page
   - Calls `HolidayFacade::toggleCalendarActive(id, !current)`
   - Visual indicator: badge showing active/inactive state
   - Add `isActive` field to index view (BooleanField or custom badge)

2. **Sync (re-import) action** — Custom action on DETAIL page
   - Opens a small form/modal asking for year (default: current year)
   - Calls `HolidayFacade::syncCalendar(countryCode, countryName, year)`
   - Flash message: "Holidays for {countryName} ({year}) synced successfully"

3. **Delete action** — Re-enable DELETE but with guard
   - Before delete: check `hasAssignedUsers()` via repository
   - If users assigned: flash error "Cannot delete — X users are assigned to this calendar"
   - If no users: proceed with deletion

4. **"Add Calendar" button** — Link on INDEX page pointing to the import controller from Step 5

**Field additions to index view:**
- `isActive` — boolean badge (green/grey)
- `lastSyncedYear` — shows which year was last imported

### Step 7 — Scheduled auto-sync command

**New command:** `Module/Holiday/Command/SyncHolidayCalendarsCommand.php`

```php
#[
    AsCommand(name: 'app:holiday:sync', description: 'Sync all active holiday calendars for the current year'),
    AsCronTask(expression: '0 2 1 1 *'),  // Jan 1st at 2:00 AM
]
class SyncHolidayCalendarsCommand
{
    public function __construct(private readonly HolidayFacadeInterface $holidayFacade) {}

    public function __invoke(): int
    {
        $year = (int) (new \DateTimeImmutable())->format('Y');
        $this->holidayFacade->syncAllActiveCalendars($year);
        return Command::SUCCESS;
    }
}
```

**Follows the scheduled command pattern** from `AbsenceBalanceResetCommand` — no `extends Command`, uses `__invoke()`, `AsCronTask` attribute.

**Schedule:** Runs once a year on January 1st. Iterates all active calendars and re-imports holidays for the new year from DateNager.

### Step 8 — Update existing import CLI command

**Changes to `ImportPublicHolidaysCommand`:**
- Refactor to use `HolidayFacade::syncCalendar()` internally (reuse same logic as admin UI)
- Keep the CLI interface for backward compatibility / automation scripts
- Add optional `--year` flag defaulting to current year (currently a required argument)

### Step 9 — Filter inactive calendars from user assignment

**Changes:**
- `Module/Admin/Form/UserProfileType.php` — filter EntityType `query_builder` to only show calendars where `isActive = true`
- `UserCrudController` (if calendar is selectable there) — same filter
- `CalculateWorkDaysQueryHandler` — no change needed. If a user has an inactive calendar assigned, holidays still apply (the calendar still exists, it's just hidden from new assignments)

---

## File Impact Summary

### New files
| File | Type |
|------|------|
| `Shared/DTO/DataNager/NagerAvailableCountryDTO.php` | DTO |
| `Module/Holiday/UseCase/Query/GetAllCalendarsQueryHandler.php` | Handler |
| `Module/Holiday/UseCase/Command/ToggleCalendarActiveCommandHandler.php` | Handler |
| `Module/Holiday/UseCase/Command/SyncCalendarCommandHandler.php` | Handler |
| `Module/Holiday/UseCase/Command/SyncAllActiveCalendarsCommandHandler.php` | Handler |
| `Module/Holiday/UseCase/Command/DeleteCalendarCommandHandler.php` | Handler |
| `Module/Holiday/Command/SyncHolidayCalendarsCommand.php` | Scheduled command |
| `Module/Admin/Controller/HolidayCalendarImportController.php` | Controller |
| `Module/Admin/Form/HolidayCalendarImportFormType.php` | Form |
| `Module/Admin/template/settings/holiday_import.html.twig` | Template |
| Doctrine migration | Migration |
| Unit tests for all new handlers, facade methods, command | Tests |
| `tests/_fixtures/Shared/DTO/DataNager/NagerAvailableCountryDTOFixture.php` | Test fixture |

### Modified files
| File | Change |
|------|--------|
| `Infrastructure/Doctrine/Entity/HolidayCalendar.php` | Add `isActive`, `lastSyncedYear` |
| `Infrastructure/Doctrine/Mapping/HolidayCalendar.orm.xml` | Add field mappings |
| `Shared/DTO/Holiday/PublicHolidayCalendarDTO.php` | Add new fields + update factories |
| `Infrastructure/Doctrine/Repository/PublicHolidayCalendarRepository.php` | Year-aware upsert, new methods |
| `Module/Holiday/Repository/PublicHolidayCalendarRepositoryInterface.php` | New method signatures |
| `Infrastructure/DataNager/Http/DateNagerClient.php` | Add `fetchAvailableCountries()` |
| `Infrastructure/DataNager/DateNagerFacade.php` | Add `getAvailableCountries()` |
| `Shared/Facade/DateNagerInterface.php` | Add method to interface |
| `Module/Holiday/HolidayFacade.php` | Add new methods |
| `Shared/Facade/HolidayFacadeInterface.php` | Add new methods |
| `Module/Admin/Controller/HolidayCalendarCrudController.php` | Enable actions, add fields |
| `Module/Holiday/Command/ImportPublicHolidaysCommand.php` | Refactor to use facade sync |
| `Module/Admin/Form/UserProfileType.php` | Filter inactive calendars |
| `tests/_fixtures/Shared/DTO/Holiday/PublicHolidayCalendarDTOFixture.php` | Add new fields |

---

## Implementation Order

Recommended sequencing — each step builds on the previous:

1. **Step 1** — Entity changes + migration (foundation for everything)
2. **Step 2** — Year-aware upsert (fixes destructive re-import)
3. **Step 3** — DateNager available countries endpoint (needed for import UI)
4. **Step 4** — Facade + handler layer (business logic)
5. **Step 5** — Admin UI: import page (primary user-facing feature)
6. **Step 6** — Admin UI: list actions — enable/disable, sync, delete
7. **Step 7** — Scheduled auto-sync command
8. **Step 8** — Refactor CLI import command
9. **Step 9** — Filter inactive calendars from user forms

Steps 1-4 are backend-only with no UI. Steps 5-6 are the admin-facing features. Steps 7-9 are operational improvements.
