# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] — 2026-04-08

The first public release of **Who's Out of Office** — a self-hosted staff leave
planner built with **Symfony 7.4** and **PHP 8.5**. One place to request time
off, see who's around, and keep the whole team in sync — without juggling
spreadsheets or paid SaaS.

### Added

#### Leave management
- End-to-end leave request workflow: **request → approve → reject → withdraw**, with status tracking and audit-friendly history
- **Configurable leave types** so each organization can model its own categories (vacation, sick, parental, etc.)
- **Smart workday calculation** that automatically skips weekends, custom non-working days, and public holidays
- **Auto-approve** for low-friction policies, with a configurable delay before requests are confirmed
- **Annual balance reset** as a scheduled job, so the new year starts clean

#### Team calendar & public holidays
- Full **team calendar view** showing absences at a glance
- **Public holiday calendars** powered by the Date Nager API — import any country with one command
- **Regional subdivision support** (e.g. `DE-BY`, `ES-CT`) so users only see holidays that actually apply to them
- **iCal subscription feed** per user — plug Who's Out of Office into Google Calendar, Apple Calendar, or Outlook

#### Slack integration
- **In-Slack approvals**: managers approve or reject requests directly from a Slack message — no context switch
- **Weekly digest** posted every Monday morning: who's out, upcoming birthdays, work anniversaries, and public holidays for the week
- **Private DMs** to requesters when their leave is approved, rejected, or auto-approved
- **Signed webhook endpoint** with full Slack signing-secret verification

#### Email notifications
- **Async transactional emails** via Symfony Messenger for pending, approved, rejected, and withdrawn requests
- **Token-based invitation emails** for onboarding new team members
- Per-user toggle to opt out of email notifications

#### Dashboard & user experience
- A redesigned **dashboard** with at-a-glance widgets: who's out today, upcoming absences, birthdays, work anniversaries, and team overview
- **Warm Teal design system** — a custom EasyAdmin theme with a polished light **and** dark mode
- **Symfony UX-powered leave request form** with an improved date picker and live workday calculation
- **User profiles** with avatar upload, working-days configuration, contract start date, and Slack member ID
- **Birthday & work anniversary celebrations** surfaced both on the dashboard and in the Slack digest
- Localized error pages that match the application theme

#### Admin & settings
- **EasyAdmin-based admin panel** for users, leave requests, leave types, and public holidays
- **YAML-based application settings** module with a dedicated admin area — change behavior without touching code
- **User invitation system** so admins can onboard team members without sharing passwords

### Security
- **Role-based access control** with distinct Admin, Manager, and Employee permissions
- **CSRF protection** using Symfony 7.4's stateless double-submit cookie pattern
- **Hash-verified iCal feeds** — calendar URLs are unguessable per user
- **Slack webhook signature verification** on every interactive request
- **Session lifetime** extended to 7 days for a smoother daily experience

### Developer experience
- **PHP 8.5** with strict types enforced project-wide
- **PHPStan level 8** static analysis
- **85% unit test coverage** with [Pest](https://pestphp.com/)
- **Pest architecture tests** enforcing module isolation, naming conventions, and clean layer dependencies
- **Functional tests** running against a real database, plus end-to-end Panther tests for the critical flows
- **One-command setup** via Docker — `just start` boots the whole stack, runs migrations, and loads dev fixtures
- Continuous integration on GitHub Actions with code coverage reporting via Codecov

### Documentation
- Public **README** with screenshots, quick start, and Slack integration guide
- **CONTRIBUTING.md** and **SECURITY.md**
- Per-module READMEs for the Admin, Settings, and Email modules
- AGPL-3.0 license

[0.1.0]: https://github.com/igornast/who-is-out-of-office/releases/tag/v0.1.0
