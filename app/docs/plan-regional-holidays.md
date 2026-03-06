# Plan: Regional Holiday Support

## Problem Statement

Currently, when a country's holiday calendar is synced (e.g., Germany), ALL holidays are stored — including regional ones that only apply to specific states/subdivisions. A user in Berlin gets holidays that only apply in Bavaria, inflating workday deductions incorrectly.

The Date Nager API already provides regional data per holiday, but the application ignores it.

## Date Nager API — Regional Data

### Holiday response fields (currently ignored)

Each holiday object from `/api/v3/PublicHolidays/{year}/{countryCode}` includes:

| Field | Type | Description |
|-------|------|-------------|
| `global` | boolean | `true` = national holiday, `false` = regional only |
| `counties` | string[] or null | Subdivision codes (e.g., `["DE-BW", "DE-BY"]`), `null` for national |
| `types` | string[] | Classification: `Public`, `Observance`, `Optional` |

### Examples (Germany 2026)

```json
// National — applies everywhere
{"date": "2026-01-01", "name": "New Year's Day", "global": true, "counties": null}

// Regional — only 3 states
{"date": "2026-01-06", "name": "Epiphany", "global": false, "counties": ["DE-BW", "DE-BY", "DE-ST"]}

// Regional — only Saarland
{"date": "2026-08-15", "name": "Assumption Day", "global": false, "counties": ["DE-SL"]}
```

### API filtering

The API also supports a query parameter: `?subdivisionCode=DE-BY` to return only holidays applicable to a specific subdivision. However, it's better to fetch all and store the metadata, so the app can filter locally without extra API calls.

### No subdivision listing endpoint

There is no dedicated endpoint to list available subdivisions per country. Subdivision codes must be extracted from the holiday data itself during sync.

---

## Current State

| Component | Current behavior | Gap |
|-----------|-----------------|-----|
| `NagerPublicHolidayDTO` | Stores `date`, `localName`, `name` | Ignores `global`, `counties` |
| `Holiday` entity | `id`, `description`, `date`, `holidayCalendar` | No regional metadata |
| `HolidayCalendar` entity | One per country | No subdivision list |
| `User` entity | `?HolidayCalendar` | No subdivision assignment |
| `DateNagerClient` | Fetches all holidays for country | Response fields dropped |
| `CalculateWorkDaysQueryHandler` | Queries by `countryCode` | Returns all holidays including inapplicable regional ones |
| `PublicHolidayRepository` | Filters by country + date range | No regional filtering |

---

## Design

### Approach

Store regional metadata on each holiday, add user subdivision preference, filter at query time.

This avoids creating separate calendars per region (which would explode the calendar count) and keeps the existing one-calendar-per-country model intact.

### Schema Changes

**`holiday` table — add 2 columns:**

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `is_global` | `TINYINT(1) NOT NULL` | `1` | Whether holiday applies nationally |
| `counties` | `JSON DEFAULT NULL` | `NULL` | Array of subdivision codes, null if global |

**`user` table — add 1 column:**

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `subdivision_code` | `VARCHAR(10) DEFAULT NULL` | `NULL` | User's subdivision (e.g., `DE-BY`) |

### Entity Changes

| Entity | Changes |
|--------|---------|
| `Holiday` | Add `bool $isGlobal = true`, `?array $counties = null` |
| `User` | Add `?string $subdivisionCode = null` |

XML mappings updated accordingly.

### DTO Changes

| DTO | Changes |
|-----|---------|
| `NagerPublicHolidayDTO` | Add `bool $global`, `?array $counties` — map from API response |
| `PublicHolidayDTO` | Add `bool $isGlobal`, `?array $counties` |
| `UserDTO` | Add `?string $subdivisionCode` |
| `PublicHolidayCalendarDTO` | No changes (calendar stays per-country) |

### Repository Changes

**`PublicHolidayRepository::findBetweenDatesForCountryCode()`**
- Add optional `?string $subdivisionCode = null` parameter
- If null: return all holidays for the country (backwards compatible)
- If set: return holidays where `isGlobal = true` OR `counties` JSON contains the subdivision code
- Use `JSON_CONTAINS()` MySQL function for the counties check

**`PublicHolidayRepository::findBetweenDatesGroupedByUser()`**
- Join user's `subdivision_code` into the query
- Filter: `h.is_global = 1 OR (u.subdivision_code IS NOT NULL AND JSON_CONTAINS(h.counties, JSON_QUOTE(u.subdivision_code)))`

**`PublicHolidayCalendarRepository::upsertByCountryCode()`**
- Persist `isGlobal` and `counties` when creating Holiday entities

### Facade & Handler Changes

**`CalculateWorkdaysQuery`** — add `?string $subdivisionCode = null`

**`CalculateWorkDaysQueryHandler`** — pass subdivision code to `getHolidayDaysForCountryBetweenDates()`

**`HolidayFacadeInterface` / `HolidayFacade`:**
- `getHolidayDaysForCountryBetweenDates()` — add optional `?string $subdivisionCode = null` parameter

**`SyncCalendarCommandHandler`** — pass `global` and `counties` from `NagerPublicHolidayDTO` through to the persisted Holiday entity

### UI Changes

**User Profile (`UserProfileType`):**
- Add `subdivisionCode` text field (or ChoiceType dropdown)
- Label: "Region/State"
- Help text: "Your region within the country (e.g., DE-BY for Bavaria). Leave empty for national holidays only."
- Only relevant when user has a holiday calendar assigned
- Mapped to User entity

**Admin — Holiday Calendar Detail page:**
- Show `Global` badge and `Counties` info in the holidays list template
- Consider showing unique subdivisions as a summary on the calendar detail

**Admin — Holiday Calendar Index page:**
- No changes needed (global/regional is per-holiday, not per-calendar)

### UserRepository::update()

Add explicit mapping for `subdivisionCode` (manual mapping pattern).

### UserDTO::fromArray()

Add `subdivisionCode` with `isset()` null safety.

---

## Implementation Phases

### Phase 1 — Data Layer (Entity, DTO, Migration)

**Step 1: Migration**
- Create migration adding `is_global` and `counties` to `holiday` table
- Add `subdivision_code` to `user` table
- Set `is_global = 1` for all existing holidays (safe default)

**Step 2: Entity + XML Mapping**
- `Holiday`: add `bool $isGlobal = true`, `?array $counties = null`
- `User`: add `?string $subdivisionCode = null`
- Update XML mappings for both entities

**Step 3: DTO updates**
- `NagerPublicHolidayDTO`: add `bool $global`, `?array $counties`, update `fromArray()`
- `PublicHolidayDTO`: add `bool $isGlobal`, `?array $counties`, update `fromEntity()`, `fromArray()`, `fromNager()`
- `UserDTO`: add `?string $subdivisionCode`, update `fromEntity()`, `fromArray()`

### Phase 2 — Sync & Storage

**Step 4: DateNagerClient**
- Response arrays already contain `global` and `counties` — no HTTP changes needed
- Update PHPDoc `@return` array shape to include new fields

**Step 5: NagerPublicHolidayDTO::fromArray()**
- Map `global` and `counties` from API response

**Step 6: Holiday creation in `PublicHolidayCalendarRepository::upsertByCountryCode()`**
- Pass `isGlobal` and `counties` when creating `Holiday` entities
- Flow: API response -> `NagerPublicHolidayDTO` -> `PublicHolidayDTO` -> `Holiday` entity

**Step 7: Re-sync command (optional)**
- After deployment, re-sync all active calendars to populate `is_global`/`counties` for existing holidays
- Existing `SyncHolidayCalendarsCommand` already handles this — just needs to be triggered manually once

### Phase 3 — Query Filtering

**Step 8: Repository queries**
- `PublicHolidayRepository::findBetweenDatesForCountryCode()`: add subdivision filtering
- `PublicHolidayRepository::findBetweenDatesGroupedByUser()`: filter by user's subdivision

**Step 9: Handler + Facade**
- `CalculateWorkdaysQuery`: add `?string $subdivisionCode`
- `CalculateWorkDaysQueryHandler`: pass to facade
- `HolidayFacadeInterface::getHolidayDaysForCountryBetweenDates()`: add parameter
- `HolidayFacade`: delegate to repository

**Step 10: Callers of CalculateWorkdaysQuery**
- Find all places that create `CalculateWorkdaysQuery` and pass the user's `subdivisionCode`
- Likely in `SaveLeaveRequestCommandHandler`, leave request form validation, etc.

### Phase 4 — UI

**Step 11: User Profile**
- Add `subdivisionCode` field to `UserProfileType`
- Add field to `UserRepository::update()` manual mapping
- Template: render in profile form

**Step 12: Admin Holiday Detail**
- Update `@AppAdmin/field/holidays_list.html.twig` to show Global/Regional badge and counties

**Step 13: Translations**
- Add labels for subdivision_code field, help text, badges

### Phase 5 — Tests

**Step 14: Unit tests**
- `CalculateWorkDaysQueryHandler` — test with subdivision filtering
- `NagerPublicHolidayDTO::fromArray()` — test new fields
- `PublicHolidayDTO::fromNager()` — test regional data passthrough

**Step 15: Architecture tests**
- Verify new fields don't break existing arch rules

**Step 16: Functional tests**
- Update fixtures to include regional holidays
- Test workday calculation with subdivision

---

## Migration Safety

- `is_global DEFAULT 1` — existing holidays treated as national (safe, slightly over-inclusive)
- `counties DEFAULT NULL` — existing holidays have no county data until re-sync
- `subdivision_code DEFAULT NULL` — existing users get all holidays (backwards compatible)
- After deployment: re-sync active calendars to populate regional metadata

## Open Questions

1. **Subdivision dropdown vs text input?** — A dropdown would be better UX, but there's no API to list subdivisions. Options:
   a. Extract unique subdivisions during sync and store on `HolidayCalendar` entity (adds a `subdivisions` JSON column)
   b. Simple text input with format hint (e.g., "DE-BY")
   c. Hardcoded mapping for common countries

2. **Should we filter `types` too?** — The API returns `Observance` and `Optional` types alongside `Public`. Currently we store all of them. Consider filtering to only `Public` type holidays during sync.

3. **Multiple subdivisions per user?** — Some users may work across regions. For now, single subdivision is simplest. Could extend to JSON array later if needed.
