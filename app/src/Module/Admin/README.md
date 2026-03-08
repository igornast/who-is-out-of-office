# Admin Module

The Admin module provides the entire web interface for the application, built on EasyAdmin Bundle. It includes the dashboard, CRUD controllers, user profile management, settings pages, calendar views, and API endpoints.

## Architecture

```
Admin/
├── Constants/
│   └── UserSettings.php               # Working days mapping
├── Controller/
│   ├── Api/                            # JSON API endpoints (POST)
│   │   ├── DisconnectSlackController.php
│   │   ├── RegenerateCalendarSubscriptionController.php
│   │   ├── ThemePreferenceController.php
│   │   └── UpdateSlackMemberIdController.php
│   ├── LeaveRequest/                   # Leave request management
│   │   ├── LeaveRequestActionController.php
│   │   ├── LeaveRequestCrudController.php
│   │   ├── LeaveRequestTypeCrudController.php
│   │   └── TeamLeaveRequestCrudController.php
│   ├── AppAbstractCrudController.php   # Base CRUD with role helpers
│   ├── AccountSecurityController.php
│   ├── AppearanceSettingsController.php
│   ├── AppSettingsController.php
│   ├── CalendarViewController.php
│   ├── DashboardController.php         # Main dashboard + menu config
│   ├── HolidayCalendarCrudController.php
│   ├── HolidayCalendarImportController.php
│   ├── IntegrationSettingsController.php
│   ├── LoginController.php
│   ├── NotificationSettingsController.php
│   ├── TeamMembersCrudController.php
│   └── UserCrudController.php
├── DTO/                                # Form data models
│   ├── AppearanceSettingsDTO.php
│   ├── ChangePasswordDTO.php
│   ├── HolidayCalendarImportDTO.php
│   ├── LeaveRequestCalculateRequestDTO.php
│   ├── NewLeaveRequestDTO.php
│   ├── UpdateThemePreferenceRequestDTO.php
│   └── UserProfileDTO.php
├── EventSubscriber/
│   ├── AdminContextForCustomRoutesSubscriber.php
│   ├── CalendarSubscriber.php
│   ├── CreateInvitationForNewUserSubscriber.php
│   ├── EasyAdminExceptionSubscriber.php
│   └── HideDeleteActionSubscriber.php
├── Form/
│   ├── DataTransformer/
│   │   └── DateRangeToStartEndTransformer.php
│   ├── AppearanceSettingsFormType.php
│   ├── AppSettingsFormType.php
│   ├── ChangePasswordFormType.php
│   ├── HolidayCalendarImportFormType.php
│   ├── NewLeaveRequestFormType.php
│   └── UserProfileType.php
├── Listener/
│   └── OnLoginCheckProfileListener.php
├── Twig/
│   └── Components/
│       ├── AbsenceWeekView.php         # Live Component — week navigator
│       └── LeaveRequestForm.php        # Live Component — request form
├── Validator/
│   ├── HasWorkdaysAndBalance.php
│   └── HasWorkdaysAndBalanceValidator.php
├── template/                           # Twig namespace: @AppAdmin
│   ├── component/
│   ├── dashboard/
│   ├── field/
│   ├── leave_request/
│   ├── page/
│   ├── settings/
│   ├── user/
│   ├── calendar_view.html.twig
│   ├── dashboard.html.twig
│   └── layout.html.twig
└── README.md
```

## Controllers

### Dashboard

| Controller | Route | Access | Description |
|---|---|---|---|
| `DashboardController` | `/app/dashboard` | `ROLE_USER` | Main dashboard, EasyAdmin menu configuration, role-scoped data |
| `LoginController` | `/login` | Public | Authentication page |

### Leave Requests

| Controller | Route | Access | Description |
|---|---|---|---|
| `LeaveRequestCrudController` | CRUD `/leave-request` | `ROLE_USER` | User's own leave requests (admins see all) |
| `TeamLeaveRequestCrudController` | CRUD `/team/leave-requests` | `ROLE_ADMIN` or `ROLE_MANAGER` | Team leave requests with approve/reject/batch actions |
| `LeaveRequestActionController` | `/app/leave-request/{id}/withdraw` | Voter | Withdraw a leave request (POST) |
| | `/app/leave-request/{id}/approve` | Voter | Approve a leave request (POST) |
| | `/app/leave-request/{id}/reject` | Voter | Reject a leave request (POST) |
| | `/app/leave-requests/new` | `ROLE_USER` | New leave request form (GET) |
| `LeaveRequestTypeCrudController` | CRUD `/leave-request-type` | `ROLE_ADMIN` | Manage absence types (name, icon, colors) |

### User Management

| Controller | Route | Access | Description |
|---|---|---|---|
| `UserCrudController` | CRUD `/my-team` | `ROLE_ADMIN` | Full user CRUD with invitation system |
| `TeamMembersCrudController` | CRUD `/team/members` | `ROLE_ADMIN` or `ROLE_MANAGER` | Read-only view of direct reports |
| `UserProfileSettingsController` | `/app/user/profile` | `ROLE_USER` | Profile edit form (name, working days, calendar, image) |

### Settings

| Controller | Route | Access | Description |
|---|---|---|---|
| `AppSettingsController` | `/app/settings` | `ROLE_ADMIN` | Application-wide settings (auto-approve, allowances) |
| `AppearanceSettingsController` | `/app/settings/appearance` | `ROLE_USER` | Theme and palette preferences |
| `AccountSecurityController` | `/app/settings/account-security` | `ROLE_USER` | Password change |
| `NotificationSettingsController` | `/app/settings/notifications` | `ROLE_ADMIN` | Slack integration status (read-only) |
| `IntegrationSettingsController` | `/app/settings/integrations` | `ROLE_ADMIN` | Integration status overview (read-only) |
| `HolidayCalendarCrudController` | CRUD `/settings/public-holidays` | `ROLE_ADMIN` | Public holiday calendar management |
| `HolidayCalendarImportController` | `/app/settings/public-holidays/import` | `ROLE_ADMIN` | Import holidays from Date Nager API |

### Calendar

| Controller | Route | Access | Description |
|---|---|---|---|
| `CalendarViewController` | `/app/calendar` | `ROLE_USER` | Interactive calendar with leave requests, holidays, birthdays |

### API Endpoints

All API controllers require `ROLE_USER` and CSRF token validation.

| Controller | Route | Description |
|---|---|---|
| `ThemePreferenceController` | `POST /app/api/user/theme` | Update theme + palette preference |
| `UpdateSlackMemberIdController` | `POST /app/api/user/slack/update` | Connect Slack member ID |
| `DisconnectSlackController` | `POST /app/api/user/slack/disconnect` | Disconnect Slack integration |
| `RegenerateCalendarSubscriptionController` | `POST /app/api/user/calendar/regenerate` | Regenerate iCal subscription URL |

## DTOs (Form Data Models)

All forms use DTOs as `data_class`. Validation constraints live on the DTO.

| DTO | Form Type | Description |
|---|---|---|
| `UserProfileDTO` | `UserProfileType` | Profile fields + image upload/removal flag |
| `NewLeaveRequestDTO` | `NewLeaveRequestFormType` | Leave type + date range (via transformer) |
| `ChangePasswordDTO` | `ChangePasswordFormType` | Current + new password (repeated) |
| `AppearanceSettingsDTO` | `AppearanceSettingsFormType` | Theme + palette enums |
| `HolidayCalendarImportDTO` | `HolidayCalendarImportFormType` | Country + year for API import |
| `LeaveRequestCalculateRequestDTO` | — | Date validation for workday calculation |
| `UpdateThemePreferenceRequestDTO` | — | API request DTO (readonly properties) |

## Event Subscribers

| Subscriber | Event | Description |
|---|---|---|
| `AdminContextForCustomRoutesSubscriber` | `KernelEvents::REQUEST` | Injects EasyAdmin `AdminContext` into custom (non-CRUD) routes so they can use EA templates |
| `CalendarSubscriber` | `SetDataEvent` | Populates calendar with leave requests, holidays, birthdays, non-working days |
| `CreateInvitationForNewUserSubscriber` | `AfterEntityPersistedEvent` | Creates invitation token when a new User is created via CRUD |
| `EasyAdminExceptionSubscriber` | `KernelEvents::EXCEPTION` | Catches EasyAdmin permission exceptions on `/app` routes, renders 403 page |
| `HideDeleteActionSubscriber` | `BeforeCrudActionEvent` | Globally disables DELETE and BATCH_DELETE on all CRUD controllers |

## Listener

| Listener | Event | Description |
|---|---|---|
| `OnLoginCheckProfileListener` | `LoginSuccessEvent` | Blocks login if user has a pending (unactivated) invitation |

## Live Components

| Component | Template | Description |
|---|---|---|
| `LeaveRequestForm` | `component/LeaveRequestForm.html.twig` | Interactive form that calculates workdays and validates balance on field change |
| `AbsenceWeekView` | `component/AbsenceWeekView.html.twig` | Week navigator showing daily absence summaries with prev/next navigation |

## Validator

| Constraint | Validator | Description |
|---|---|---|
| `HasWorkdaysAndBalance` | `HasWorkdaysAndBalanceValidator` | Validates that a date range contains workdays and user has sufficient leave balance (skipped for non-balance-affecting leave types) |

## Data Scoping by Role

The module scopes data visibility based on user role:

| Role | Dashboard | CRUD Lists | Leave Requests |
|---|---|---|---|
| `ROLE_ADMIN` | All users, all data | All users | All requests |
| `ROLE_MANAGER` | Direct reports only | Direct reports only | Team requests |
| `ROLE_USER` | Own data only | — | Own requests |

`DashboardController::getTeamUserIds()` returns `null` for admins (global scope) or an array of direct report IDs for managers.

## Template Structure

Templates use the Twig namespace `@AppAdmin` (configured in `twig.yaml` → `src/Module/Admin/template/`).

```
template/
├── layout.html.twig                      # Base layout, extends @EasyAdmin/layout.html.twig
├── dashboard.html.twig                   # Dashboard with partial includes
├── dashboard/
│   ├── component/
│   │   └── _user_avatar.html.twig        # Reusable avatar macro
│   ├── _welcome_banner.html.twig
│   ├── _my_team.html.twig
│   ├── _upcoming_absences.html.twig
│   ├── _upcoming_birthdays.html.twig
│   ├── _whos_out_today.html.twig
│   ├── _recent_requests.html.twig
│   ├── _leave_balances.html.twig
│   └── _quick_links.html.twig
├── component/
│   ├── LeaveRequestForm.html.twig        # Live Component
│   ├── AbsenceWeekView.html.twig         # Live Component
│   ├── _leave_request_confirm_modal.html.twig
│   └── _role_badge.html.twig
├── settings/
│   ├── edit.html.twig                    # App settings (admin)
│   ├── appearance.html.twig              # Theme/palette (user)
│   ├── account_security.html.twig        # Password change (user)
│   ├── notifications.html.twig           # Slack status (admin)
│   ├── integrations.html.twig            # Integration status (admin)
│   └── holiday_import.html.twig          # Holiday import (admin)
├── user/
│   └── profile_edit.html.twig            # User profile form
├── leave_request/
│   └── new.html.twig                     # New leave request page
├── field/
│   └── holidays_list.html.twig           # EasyAdmin custom field
├── page/
│   └── login.html.twig                   # Standalone login page
└── calendar_view.html.twig               # Calendar with filters
```
