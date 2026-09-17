# Who's Out of Office

[![CI](https://github.com/igornast/who-is-out-of-office/actions/workflows/php.yml/badge.svg)](https://github.com/igornast/who-is-out-of-office/actions/workflows/php.yml)
[![codecov](https://codecov.io/gh/igornast/who-is-out-of-office/graph/badge.svg)](https://codecov.io/gh/igornast/who-is-out-of-office)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-brightgreen)
![PHP](https://img.shields.io/badge/PHP-8.5-blue)
![License](https://img.shields.io/github/license/igornast/who-is-out-of-office)

A self-hosted staff leave planner built with Symfony 7.4. Manage leave requests, team calendars, public holidays, and Slack notifications — all in one place.

**[www.whoisooo.app](https://www.whoisooo.app)**

![Dashboard](docs/screenshots/dashboard.png)

**Features:**
- Leave request workflow with approval/rejection
- Role-based access — Admin, Manager, Employee
- Slack integration — in-channel approvals, weekly digest, and status auto-sync
- Public holiday calendars with regional subdivision support
- iCal feed export per user
- Email notifications
- Two-factor authentication (TOTP + backup codes)

## Quick Start

```bash
git clone https://github.com/igornast/who-is-out-of-office.git
cd who-is-out-of-office
just start
```

The PHP container handles everything on first boot: installs dependencies, waits for the database, runs migrations, and loads dev fixtures.

The app is available at **`http://localhost/app/dashboard`**.

For full setup details and how to run tests, see [CONTRIBUTING.md](CONTRIBUTING.md).

## Admin Account

The dev fixtures include a default admin account for initial access:

- **Email:** `admin@whoisooo.app`
- **Password:** `123`

> ⚠️ **Important:** This account is only available in development (fixtures are never loaded in production).
> Create your own admin account before going live — see
> [Create the first admin account](#3-create-the-first-admin-account).


## Production Deployment

> ### ⚠️ Never run the development stack against production data
>
> The default `docker-compose.yml` is a **development** stack. Its entrypoint runs
> `doctrine:database:drop --force` and reloads demo fixtures **on every container
> start** — a single `docker compose up` with that file wipes a production database
> irrecoverably.
>
> Two things keep the two stacks apart, and you need both:
>
> 1. **`COMPOSE_FILE=docker-compose.prod.yml` in the root `.env`** (it ships in
>    `.env.dist`). This pins every *bare* `docker compose …` command in the checkout
>    to the production stack, so `docker compose up -d` is safe.
> 2. **Never pass `-f docker-compose.yml`** in a production checkout, and never run
>    `docker compose` from a directory where the root `.env` is missing. The
>    `COMPOSE_FILE` guard does not apply in either case.
>
> The two stacks also use separate Docker volumes and separate container names, so
> they cannot silently share a database. See
> [Moving an existing dev install to production](#moving-an-existing-dev-install-to-production).

For a real deployment use `docker-compose.prod.yml`, which builds the `prod` image
target, mounts no source volumes, runs with `APP_ENV=prod`, and includes the
background workers.

### 1. Configure

Two separate files are involved:

- **`.env`** at the repository root — read by docker compose itself, for the database
  container's credentials and the `COMPOSE_FILE` pin. `cp .env.dist .env` and edit it.
- **`app/.env.local`** — read by Symfony. Create it with at least:

```dotenv
APP_ENV=prod
APP_SECRET=<run: openssl rand -hex 16>
APP_BASE_URL=https://leave.example.com
TRUSTED_PROXIES=<your reverse proxy's IP or CIDR>
TRUSTED_HOSTS='^leave\.example\.com$'
DATABASE_URL="mysql://ooo:<MYSQL_PASSWORD>@db:3306/ooo_db?serverVersion=8.4.4&charset=utf8mb4"
MAILER_DSN=smtp://user:pass@smtp.example.com:587
EMAIL_FROM_ADDRESS=noreply@example.com
EMAIL_FROM_NAME="Who's OOO"
TOTP_ENCRYPTION_KEY=<run: openssl rand -base64 32>
ICAL_SECRET=<run: openssl rand -hex 16>
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

Deactivated users cannot log in, and their existing sessions end on their next request.
Older installs may have people whose `is_active` flag is still `0`, because the column was
added without a backfill. Before upgrading, list the accounts that will be locked out
(read-only):

```sql
SELECT u.email, u.created_at
FROM user u
LEFT JOIN invitation i ON i.user_id = u.id
WHERE u.is_active = 0 AND i.id IS NULL;
```

Reactivate the people on that list who should keep access (**Team Members → Edit**).
After the upgrade they cannot log in until an admin reactivates them.

Use at least 32 characters for `ICAL_SECRET` (`openssl rand -hex 16`); workers log an error
in production when it is shorter. On an existing install, changing it invalidates every
calendar subscription URL your users have already added.

Do not `cp app/.env app/.env.local` — that file ships `APP_ENV=dev` and a MailPit
`MAILER_DSN`. The credentials in `DATABASE_URL` must match the `MYSQL_*` values in
the root `.env`.

### TLS and reverse proxies

**This app does not terminate TLS.** `docker-compose.prod.yml` publishes plain HTTP on
port `80`; you are expected to put a TLS-terminating reverse proxy (nginx, Caddy,
Traefik, a cloud load balancer…) in front of it and let that proxy speak HTTPS to the
outside world.

When you do, you **must** set `TRUSTED_PROXIES` in `app/.env.local` to the address the
proxy connects from. Without it Symfony ignores the `X-Forwarded-*` headers, believes every
request arrived over plain `http`, and consequently:

- session cookies are issued **without the `Secure` flag**;
- absolute URLs generated inside a request come out as `http://`.

```dotenv
# One or more comma-separated IPs / CIDRs — only the proxy's own address.
# 172.18.0.1 below is an example; replace it with your reverse proxy's address
# or your compose network gateway.
TRUSTED_PROXIES=172.18.0.1
```

Do **not** use `REMOTE_ADDR` or `PRIVATE_SUBNETS` unless port 80 is reachable by the proxy
and nothing else. Trusting a client that is not your proxy lets it forge its IP, scheme and
host. `docker-compose.prod.yml` publishes port 80 on all interfaces (IPv4 and IPv6) by
default; when the proxy runs on the same host, set `HTTP_BIND_ADDRESS=127.0.0.1` in the
root `.env`.

Also set `TRUSTED_HOSTS` to a regular expression matching your public host name(s).
Symfony then rejects requests carrying any other `Host` header with a `400`. It is empty
by default, which accepts any host:

```dotenv
TRUSTED_HOSTS='^leave\.example\.com$'
```

`APP_BASE_URL` is separate and still required: it is what CLI-generated links (emails
sent from workers and cron) use, since those run outside any HTTP request. It must have no
trailing slash.

### 2. Start

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec php bin/console doctrine:migrations:migrate --no-interaction
```

### 3. Create the first admin account

Fixtures are never loaded in prod and the app is invitation-only, so a fresh install
has no way to log in until you insert an admin row by hand. Every later account can be
created through the UI (**Team Members → Add**, which sends an invitation email).

> This is a known rough edge. A `bin/console app:user:create-admin` bootstrap command
> would be a sensible follow-up; until then, the recipe below is the supported path.

First generate an Argon2id hash for the password you want:

```bash
docker compose -f docker-compose.prod.yml exec php \
    bin/console security:hash-password 'your-password' 'App\Infrastructure\Doctrine\Entity\User'
```

Copy the `Password hash` value. Then open a MySQL shell **inside the container** and
paste the statement below — do not try to inline the hash into a host shell command,
because it is full of `$` characters that the host shell will expand and silently
corrupt:

```bash
docker compose -f docker-compose.prod.yml exec db mysql -u root -p ooo_db
```

```sql
INSERT INTO user (
    id, first_name, last_name, email, password, roles,
    annual_leave_allowance, current_leave_balance,
    is_active, is_email_notifications_enabled, celebrate_work_anniversary,
    working_days, backup_codes, is_two_factor_enabled,
    absence_balance_reset_day, theme_preference, palette_preference,
    created_at, updated_at
) VALUES (
    UUID(), 'Ada', 'Lovelace', 'admin@example.com',
    '$argon2id$v=19$m=65536,t=4,p=1$REPLACE$WITH_THE_HASH_FROM_ABOVE',
    '["ROLE_ADMIN"]',
    30, 30,
    1, 1, 1,
    '[1, 2, 3, 4, 5]', '[]', 0,
    MAKEDATE(YEAR(CURRENT_DATE()), 1), 'auto', 'teal',
    NOW(), NOW()
);
```

Notes on the values:

- Every column listed is `NOT NULL`. Most have no usable default, so they must be
  supplied; `theme_preference`, `palette_preference` and `is_two_factor_enabled` do
  have defaults and are spelled out only so the row is explicit. Everything omitted
  (`profile_image_url`, `birth_date`, `contract_started_at`, `manager_id`,
  `holiday_calendar_id`, `totp_secret`, `subdivision_code`, …) is nullable and can be
  filled in later from the profile page.
- `roles` and `working_days` are JSON columns — the quoting above is exact.
  `ROLE_USER` is added automatically at runtime, so `["ROLE_ADMIN"]` is enough.
- `is_active` **must** be `1`; inactive users are hidden from team lists and calendars.
- `working_days` is a list of ISO weekday numbers (`1` = Monday).
- `absence_balance_reset_day` is the yearly leave-balance reset date; 1 January of the
  current year is the usual choice.

Log in at `https://your-domain/login`, then change the password and set up 2FA from
**Profile → Security**.

### The background workers are required

Emails, Slack notifications and every scheduled job are dispatched through Symfony
Messenger. **If nothing consumes the queue, no email is ever sent** — the messages
simply accumulate in the `messenger_messages` table. Two services handle this:

| Service | Transport | What breaks without it |
|---|---|---|
| `worker-async` | `async` | Invitation emails, leave-request notification emails, auto-approval messages |
| `worker-scheduler` | `scheduler_default` | Leave-request auto-approve (5 min), Slack status sync (20 min), `app:feed:sync` (6 h), password-reset-token cleanup and absence-balance reset (daily), holiday-calendar sync (yearly) |
| `worker-scheduler` | `scheduler_weekly_digest` | The Slack weekly digest |

They are split because the scheduler must not be restarted on a timer: schedules are
stateless, so a restart recomputes the next run from "now" and can skip a job that
was due during the gap.

Check they are alive with:

```bash
docker compose -f docker-compose.prod.yml logs worker-async worker-scheduler
```

A growing `messenger_messages` table is the symptom of a stopped worker. Messages that
fail repeatedly land in the `failed` queue instead of being discarded:

```bash
docker compose -f docker-compose.prod.yml exec php bin/console messenger:failed:show
docker compose -f docker-compose.prod.yml exec php bin/console messenger:failed:retry
```

### Testing your mail configuration

Symfony's built-in `mailer:test` command hardcodes `from@example.org` as the sender,
which many SMTP providers reject. Pass your own sender explicitly:

```bash
docker compose -f docker-compose.prod.yml exec php bin/console mailer:test you@example.com --from noreply@example.com
```

### Backups

All application data lives in the `whoisooo-prod_mysql_prod` named volume, and uploaded
profile images in `whoisooo-prod_uploads`. Neither is backed up for you:

```bash
docker compose -f docker-compose.prod.yml exec -T db \
    sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" ooo_db' \
    | gzip > backup-$(date +%F).sql.gz
```

The `sh -c '…'` wrapper with **single** quotes is deliberate: `$MYSQL_ROOT_PASSWORD` must
expand inside the container, where the variable is set. Written as
`-p"$MYSQL_ROOT_PASSWORD"` directly, your host shell expands it first — and since the
root `.env` is not exported into your shell, it expands to an empty password and the
dump fails.

Profile images:

```bash
docker run --rm -v whoisooo-prod_uploads:/data -v "$PWD":/backup alpine \
    tar czf /backup/uploads-$(date +%F).tar.gz -C /data .
```

### Moving an existing dev install to production

The development and production stacks deliberately use **different Docker volumes**
(`who-is-out-of-office_mysql` vs `whoisooo-prod_mysql_prod`) and different container
names. Switching a running dev install to `docker-compose.prod.yml` therefore starts
against an **empty database** — your data does not migrate itself, and MySQL will not
re-apply the `MYSQL_*` credentials to an already-initialised datadir either.

Dump from the old volume and restore into the new one:

```bash
# 1. Dump from the dev stack (dev db has no root password)
docker compose -f docker-compose.yml exec -T db \
    sh -c 'exec mysqldump -u root ooo_db' > dev-dump.sql

# 2. Bring up the prod stack and run migrations
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec php bin/console doctrine:migrations:migrate --no-interaction

# 3. Restore
docker compose -f docker-compose.prod.yml exec -T db \
    sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" ooo_db' < dev-dump.sql
```

Copy `whoisooo-prod_uploads` across the same way if the dev install has profile images
worth keeping. **Stop the dev stack before you start the prod one** — both publish port
`80`.

## Application Settings

The application uses a YAML-based settings system that lets administrators change application behavior without code changes.

**Available Settings:**
- `leave_request.auto_approve` - Enable/disable automatic approval of leave requests
- `leave_request.auto_approve_delay` - Delay in seconds before automatically approving leave requests
- `leave_request.default_annual_allowance` - Default number of annual leave days for new users
- `leave_request.min_notice_days` - Minimum days notice required for a leave request (0 = no minimum)
- `leave_request.max_consecutive_days` - Maximum consecutive days allowed per leave request (0 = unlimited)
- `notification.skip_weekend_holidays` - Skip public holidays that fall on weekends in notifications
- `slack.status_sync_enabled` - Enable/disable the Slack status auto-sync feature
- `organization.name` - Display name of your organization

**Managing Settings:**
1. Log in with an admin account
2. Navigate to **App Settings** in the sidebar menu
3. Update settings and click **Save Changes**

Settings are stored in `app/src/Module/Settings/Config/app_setting.yaml` and can be relocated using the `APP_SETTINGS_FILE` environment variable.

📖 **[Read the detailed Settings documentation](app/src/Module/Settings/README.md)** for architecture details, adding new settings, and advanced configuration.

## Public Holiday Import

Public holidays can be imported via the admin UI:

1. Log in with an admin account
2. Navigate to **Public Holidays** in the Settings sidebar section
3. Click **Add Calendar**, select a country and year, then import

Alternatively, use the CLI command:

```shell
php app/bin/console app:holiday:import DE Germany 2025
```

## Frontend & Assets
The project uses Symfony AssetMapper and Symfony UX for JavaScript, CSS, and components.

After deployment, compile the assets:

```shell
php app/bin/console asset-map:compile
```

Side notes:
* Assets live in `assets/` and importmap.php. 
* Remote packages (e.g. Stimulus, UX components) are resolved at compile time. 
* Do not edit files in `public/assets/`, they are generated.

For details, see [AssetMapper](https://symfony.com/doc/current/frontend/asset_mapper.html) and [Symfony UX](https://ux.symfony.com).



## Slack Integration

This section describes how to integrate Slack notifications into the leave‑planner application.

---

### 1. Configuration

1. Make sure to define these environment variables in your `.env.local` or server environment:

```dotenv
###> symfony/notifier ###
SLACK_DSN=""
SLACK_SIGNING_SECRET=""
# Channel for absence request approval notifications
SLACK_AR_APPROVE_CHANNEL_ID=""
# Channel for the absences daily digest
SLACK_AR_HR_DIGEST_CHANNEL_ID=""
###< symfony/notifier ###
```

---

### 2. Sending Notifications

The app uses two channels to communicate with the company members.
* SLACK_AR_APPROVE_CHANNEL_ID - channel used for in-slack approval actions. Managers can approve or reject requests
  directly from the slack integration bot.
* SLACK_AR_HR_DIGEST_CHANNEL_ID - weekly digest with absences and birthdays information.

---

### 3. Verifying Incoming Requests

The app uses `SLACK_SIGNING_SECRET` to verify if the incoming api messages has been sent by the absence bot app.
For more details on the implementation check `RequestVerifier` class in the slack module.

---

### 4. User‑Specific DMs

Once a user has configured their `slackMemberId` and enabled the custom app, the bot can send them private updates.

---

### 5. Weekly Digest (Scheduled Task)

The `slack:weekly_digest` command posts a summary digest. Its schedule (day, time,
timezone) is configured in **Application Settings** and dispatched by a dedicated Symfony
Scheduler schedule named `weekly_digest`. Production must consume both scheduler transports:

    php bin/console messenger:consume scheduler_default scheduler_weekly_digest

Schedule changes take effect on the next scheduler tick (no restart needed).

`php bin/console slack:weekly_digest`

The bot will post a summary of:

- Who is out this week.
- Birthdays for this week.
- Fallback message if no absences or birthdays.

---

### 6. Slack Bot Setup & Approval Workflow

1. **Install the Leave Planner Bot**
   - Create a new Slack App and add it to your workspace.
   - Generate an OAuth token and set `SLACK_DSN` to include it.
   - Grant the bot the `chat:write` scope.
   - Learn more about OAuth setup here: https://api.slack.com/authentication/oauth-v2

2. **Enable Interactivity**
   - In your Slack App settings, navigate to **Interactivity & Shortcuts**.
   - Set the **Request URL** to:
     ```
     https://your-domain.com/api/slack/interactive-endpoint
     ```

3. **Post Approval Requests**
   - Whenever someone submits a leave request, the bot will announce it in the approval channel.
   - The message includes **Approve** and **Reject** buttons for managers.

4. **Handle Button Clicks**
   - When a manager clicks **Approve** or **Reject**, Slack sends a `block_actions` payload to the interactive endpoint.
   - Leave Planner app:
      1. Verifies the Slack signature.
      2. Reads the action button `value`.
      3. Updates the leave request status in the system.
      4. Updates the original Slack message to reflect the outcome.

5. **Notify the Requester**
   If the user has provided a Slack member ID, the bot will send them a direct message with the updated request status.

---

### 7. Slack Status Auto-Sync

Automatically set a user's Slack status (emoji + text) when they are on approved leave, and clear it when the leave ends or is cancelled. This feature requires a **paid Slack workspace**.

#### How it works

A background command (`slack:sync-statuses`) runs every 20 minutes and:

- **Sets** the Slack status for users whose approved leave is currently active and hasn't been synced yet. The status shows the leave type name and end date (e.g. "Vacation until Apr 18") with a configurable emoji.
- **Clears** the Slack status for users whose leave has ended or is no longer approved (rejected, withdrawn).

The sync is idempotent — each leave request is tracked with an internal flag, so statuses are never set twice and the command safely catches up after downtime.

#### Prerequisites

- A **paid Slack workspace** (free plans do not allow setting another user's status).
- The Slack App must be configured with an **OAuth redirect URL** pointing to your instance.
- Each user must have their **Slack Member ID** linked in their profile settings.

#### Admin setup

1. Add these environment variables to `.env.local`:

   ```dotenv
   ###> app/slack-status-sync ###
   SLACK_CLIENT_ID="your-slack-app-client-id"
   SLACK_CLIENT_SECRET="your-slack-app-client-secret"
   SLACK_TOKEN_ENCRYPTION_KEY="base64-encoded-32-byte-key"
   ###< app/slack-status-sync ###
   ```

   To generate the encryption key, run:

   ```bash
   php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"
   ```

   Copy the output and paste it as the `SLACK_TOKEN_ENCRYPTION_KEY` value. This key is used to encrypt the admin OAuth token at rest — keep it secret and do not rotate it without re-authorizing.

2. In your Slack App settings, add the OAuth redirect URL:
   ```
   https://your-domain.com/app/settings/slack-status-sync/oauth/callback
   ```

3. Add the `users.profile:write` **user scope** to your Slack App (under OAuth & Permissions > User Token Scopes).

   > **Important:** Adding a new scope requires reinstalling the Slack App to your workspace. This generates a new **Bot User OAuth Token**, so you must update the `SLACK_DSN` environment variable in production with the new token.

4. Log in as admin and go to **Settings > Integrations**.

5. Click **Authorize status sync** — you'll be redirected to Slack to grant permission. The admin account performing this step must be a **Slack workspace Owner or Admin**.

6. After authorization, the status shows as "Active" and the feature is live.

#### Configuring leave type emojis

Each leave type can have a custom Slack emoji code (e.g. `:palm_tree:`, `:face_with_thermometer:`). Set this in **Leave Request Types > Edit** via the "Slack status emoji" field. If left blank, the default `:calendar:` emoji is used.

#### User opt-out

Users can disable status sync for their account in **Profile Settings** — a toggle appears when the admin has authorized status sync and the user has linked their Slack Member ID.

#### Revoking access

An admin can revoke the status sync authorization at any time from **Settings > Slack Status Sync > Revoke**. This immediately disables the feature for all users. If the admin token becomes invalid (e.g. the authorizing user is removed from Slack), the app detects this automatically and disables the feature.

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for setup instructions, coding standards, and how to submit changes.

## License

[AGPL-3.0](LICENSE)
