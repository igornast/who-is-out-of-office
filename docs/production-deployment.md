# Production deployment

This guide covers running Who's OOO in production with `docker-compose.prod.yml`. That file
builds the `prod` image target, mounts no source code, sets `APP_ENV=prod`, and starts one
Messenger worker next to PHP-FPM, nginx and MySQL. The PHP container compiles the asset map on
every start, so there's no separate asset build step.

The stack serves plain HTTP on port 80. TLS is your reverse proxy's job (see
[step 2](#2-put-a-tls-proxy-in-front)).

Most of this is standard Symfony and Docker. Two parts can cost you data or access if you skip
them: the first section below and the admin account step.

## Keep the development stack away from production data

> [!WARNING]
> The default `docker-compose.yml` is the development stack. Its entrypoint runs
> `doctrine:database:drop --force` and reloads demo fixtures every time the container starts.
> That's great for development. Against production, one `docker compose up` with that file
> wipes the database, and there's no undo.

You need both of these safeguards:

1. Keep `COMPOSE_FILE=docker-compose.prod.yml` in the root `.env`. It ships in `.env.dist`
   and pins every bare `docker compose …` command in the checkout to the production stack,
   so a plain `docker compose up -d` is safe.
2. Never pass `-f docker-compose.yml` in a production checkout, and never run
   `docker compose` from a directory without the root `.env`. The `COMPOSE_FILE` pin does
   nothing in either case.

The two stacks also use separate Docker volumes and container names, so they can't end up
sharing a database by accident. If you already run a dev install and want to promote it, see
[Moving a dev install to production](#moving-a-dev-install-to-production).

## 1. Configure

There are two files, and different programs read them.

The root `.env` is read by Docker Compose. It holds the database container's credentials and
the `COMPOSE_FILE` pin. Create it with `cp .env.dist .env` and change the passwords.

`app/.env.local` is read by Symfony. Create it with at least:

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

The user, password and database name in `DATABASE_URL` must match the `MYSQL_*` values in the
root `.env`.

Don't start from a copy of `app/.env`. That file sets `APP_ENV=dev` and points `MAILER_DSN`
at MailPit.

`APP_BASE_URL` takes no trailing slash. Emails sent from the worker and from scheduled jobs
run outside any HTTP request, so they build their links from this value.

Use at least 32 characters for `ICAL_SECRET`. `openssl rand -hex 16` gives exactly 32, and the
app logs a warning in production when the secret is shorter. On an existing install, changing
`ICAL_SECRET` breaks every calendar subscription URL your users have already added.

The Slack variables are optional. The [Slack section of the README](../README.md#slack-integration)
lists them.

## 2. Put a TLS proxy in front

The app doesn't terminate TLS. Put nginx, Caddy, Traefik or a cloud load balancer in front of
it and let that proxy handle HTTPS. You probably run one already.

Once the proxy is in place, set `TRUSTED_PROXIES` in `app/.env.local` to the address the proxy
connects from. Without it, Symfony ignores the `X-Forwarded-*` headers and treats every request
as plain `http`. Session cookies then lose the `Secure` flag, and absolute URLs built during a
request start with `http://`.

```dotenv
# One or more comma-separated IPs or CIDRs, covering only the proxy itself.
# 172.18.0.1 is an example: use your proxy's address or your compose network gateway.
TRUSTED_PROXIES=172.18.0.1
```

Avoid `REMOTE_ADDR` and `PRIVATE_SUBNETS` unless port 80 is reachable by the proxy and nothing
else. If you trust any address other than your proxy, clients can forge their IP, scheme and
host. `docker-compose.prod.yml` publishes port 80 on every interface (IPv4 and IPv6) by
default. If the proxy runs on the same host, set `HTTP_BIND_ADDRESS=127.0.0.1` in the root
`.env`.

Set `TRUSTED_HOSTS` to a regular expression that matches your public host name. Symfony then
answers `400` to any request with a different `Host` header. When the variable is empty (the
default), every host is accepted.

```dotenv
TRUSTED_HOSTS='^leave\.example\.com$'
```

`TRUSTED_HOSTS` doesn't replace `APP_BASE_URL`; you need both.

## 3. Start

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec php bin/console doctrine:migrations:migrate --no-interaction
```

The worker waits for the `messenger_messages` table before it starts consuming, so it sits
idle until the migrations have run.

## 4. Create the first admin account

Production never loads fixtures, and new accounts are created by invitation only. A fresh
install therefore has nobody who can log in until you insert an admin row by hand. After that,
admins add everyone else from the UI (Team Members → Add, which sends an invitation email).

> [!WARNING]
> The very first migration inserts an inactive admin, `admin@whoisooo.app`, into every install.
> It's a leftover from the project's early days, and its password hash is public in this
> repository. Never reactivate it. Delete it instead:
>
> ```sql
> DELETE FROM user WHERE email = 'admin@whoisooo.app' AND is_active = 0;
> ```
>
> If MySQL refuses because other rows reference the account, leave it inactive.

> [!NOTE]
> I know this step is clunky. A `bin/console app:user:create-admin` command would replace it,
> but until that exists, the steps below are the supported path.

Generate an Argon2id hash for the password you want:

```bash
docker compose -f docker-compose.prod.yml exec php \
    bin/console security:hash-password 'your-password' 'App\Infrastructure\Doctrine\Entity\User'
```

Copy the `Password hash` value. Then open a MySQL shell inside the container and paste the
statement below there. Don't put the hash into a command on your host shell: it contains `$`
characters, and the host shell will expand them and corrupt the hash without any error.

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

About the values:

- Every listed column is `NOT NULL`, and most have no usable default. `theme_preference`,
  `palette_preference` and `is_two_factor_enabled` do have defaults; they are listed so the row
  is explicit. The omitted columns (`profile_image_url`, `birth_date`, `contract_started_at`,
  `manager_id`, `holiday_calendar_id`, `totp_secret`, `subdivision_code` and others) are
  nullable, and you can fill them in later from the profile page.
- `roles` and `working_days` are JSON columns, so keep the quoting exactly as shown. The app
  adds `ROLE_USER` at runtime, so `["ROLE_ADMIN"]` is enough.
- `is_active` must be `1`. Inactive users can't log in and are hidden from team lists and
  calendars.
- `working_days` lists ISO weekday numbers (`1` is Monday).
- `absence_balance_reset_day` is the date the yearly leave balance resets. Most installs use
  1 January of the current year.

Log in at `https://your-domain/login`, change the password, and turn on 2FA from Security in
the Account section of the sidebar.

## Background workers

Emails, auto-approvals and every scheduled job (including the Slack status sync and weekly
digest) go through Symfony Messenger. If nothing consumes the queue, no email is sent: the
messages pile up in the `messenger_messages` table. One service, `worker`, consumes it:

| Service | Transport | What stops working without it |
|---|---|---|
| `worker` | `scheduler_default` | Leave request auto-approve (5 min), Slack status sync (20 min), `app:feed:sync` (6 h), password reset token cleanup and absence balance reset (daily), holiday calendar sync (yearly) |
| `worker` | `scheduler_weekly_digest` | The Slack weekly digest |
| `worker` | `async` | Invitation emails, leave request notification emails, auto-approval messages |

I run one process that polls the scheduler transports first, then `async`, so the schedulers are
checked between async messages. The worker has no time limit because schedules keep no state, and
a restart can skip a job that fell due while the worker was down. I never scale it past one copy,
because each copy dispatches every scheduled job. Scheduled jobs run inside the same process, so
a long job (for example the holiday sync or the weekly digest) delays queued emails until it
finishes.

Check that it is running:

```bash
docker compose -f docker-compose.prod.yml logs worker
```

A `messenger_messages` table that keeps growing means the worker has stopped. Messages that fail
repeatedly are moved to the `failed` queue, not thrown away:

```bash
docker compose -f docker-compose.prod.yml exec php bin/console messenger:failed:show
docker compose -f docker-compose.prod.yml exec php bin/console messenger:failed:retry
```

## Testing your mail configuration

Symfony's `mailer:test` command sends from `from@example.org` unless told otherwise, and many
SMTP providers reject that address. Pass your own sender:

```bash
docker compose -f docker-compose.prod.yml exec php bin/console mailer:test you@example.com --from noreply@example.com
```

## Application settings

App Settings are stored in a YAML file on the `whoisooo-prod_settings` volume, at
`/var/www/settings/app_setting.yaml`. The `php` and `worker` containers mount the same
volume. In practice, this means a change saved in the UI reaches the scheduled jobs on their
next run and survives `up --build`.

On first start, the containers copy the defaults from the image onto the empty volume. After
that, the file on the volume wins and later images never overwrite it.

`docker-compose.prod.yml` sets `APP_SETTINGS_FILE` for these containers, which overrides any
value in `app/.env.local`. If you kept your own settings file somewhere else, copy it onto the
volume once:

```bash
docker compose -f docker-compose.prod.yml cp my-settings.yaml php:/var/www/settings/app_setting.yaml
docker compose -f docker-compose.prod.yml exec php chown www-data:www-data /var/www/settings/app_setting.yaml
```

## Upgrading an existing install

Deactivated users can't log in, and an active session ends on the user's next request after
deactivation. The `is_active` column was added without a backfill, so on older installs some
people may still have it set to `0`. Before you upgrade, run this read-only query to list the
accounts that will be locked out:

```sql
SELECT u.email, u.created_at
FROM user u
LEFT JOIN invitation i ON i.user_id = u.id
WHERE u.is_active = 0 AND i.id IS NULL;
```

Reactivate the people on that list who should keep access (Team Members → Edit). Anyone you
miss can't log in after the upgrade until an admin reactivates them. Don't fix this with a
blanket `UPDATE user SET is_active = 1`: that also reactivates accounts that were deactivated
on purpose.

If the list includes `admin@whoisooo.app`, don't reactivate it; delete it as described in
[Create the first admin account](#4-create-the-first-admin-account).

### Leave balances added by mistake before this fix

Older versions could add one year's allowance too many to a user's balance:

- A user created in the admin panel got a balance reset day of 1 January of the year they were
  created. That date had already passed, even for someone created on 1 January, because the
  reset runs at midnight. The first nightly reset after the user was active added a second
  allowance on top of the one they were created with.
- The upgrade that introduced the reset day (migration `Version20260207000000`) set it to
  1 January of the year it ran for every existing user. The first nightly reset after that
  upgrade added an allowance to every active user, and to inactive users once they were
  activated. If you had already set balances for that year, that allowance was one too many.

Each affected user got exactly one extra allowance. Resets add to the balance, so that extra
allowance is still in it today. Users created after their reset day who haven't been activated yet
(for example, invited users created this year who haven't accepted) are handled by the fix and
won't receive it.

This read-only query lists everyone with their balance, oldest account first:

```sql
SELECT email, is_active, created_at, annual_leave_allowance, current_leave_balance,
       absence_balance_reset_day
FROM user
ORDER BY created_at;
```

Check each balance and correct any that are too high in My Organization → Edit. Don't fix this
with a blanket `UPDATE`: some admins changed the reset day when creating a user, or adjusted the
balance afterwards, and those balances are already correct.

## Backups

Nothing is backed up for you. Application data lives in the `whoisooo-prod_mysql_prod` volume,
uploaded profile images in `whoisooo-prod_uploads` and App Settings in `whoisooo-prod_settings`.

Database:

```bash
docker compose -f docker-compose.prod.yml exec -T db \
    sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" ooo_db' \
    | gzip > backup-$(date +%F).sql.gz
```

Keep the single quotes around the `sh -c` argument. `$MYSQL_ROOT_PASSWORD` is only set inside
the container, so it has to expand there. Without the wrapper, your host shell expands it
first; the root `.env` isn't exported to your shell, so the password comes out empty and the
dump fails.

Profile images:

```bash
docker run --rm -v whoisooo-prod_uploads:/data -v "$PWD":/backup alpine \
    tar czf /backup/uploads-$(date +%F).tar.gz -C /data .
```

App Settings:

```bash
docker compose -f docker-compose.prod.yml cp php:/var/www/settings/app_setting.yaml app_setting-$(date +%F).yaml
```

## Moving a dev install to production

The development and production stacks use different Docker volumes
(`who-is-out-of-office_mysql` and `whoisooo-prod_mysql_prod`) and different container names.
If you switch a dev install to `docker-compose.prod.yml`, it starts with an empty database, and
the production stack can't be pointed at the dev volume either: MySQL won't apply new
`MYSQL_*` credentials to a data directory that is already initialised. Move the data with a
dump and restore.

These are the only commands in this guide that use `-f docker-compose.yml`. Be careful with
them. Starting the dev `php` container drops the database, so if the dev stack is already
stopped, start only its `db` service (`docker compose -f docker-compose.yml up -d db`) before
you dump.

```bash
# 1. Dump the dev database (it has no root password), then stop the dev stack.
#    Both stacks publish port 80, so they cannot run side by side.
docker compose -f docker-compose.yml exec -T db \
    sh -c 'exec mysqldump -u root ooo_db' > dev-dump.sql
docker compose -f docker-compose.yml stop

# 2. Start only the production database and restore the dump into it
docker compose -f docker-compose.prod.yml up -d --wait db
docker compose -f docker-compose.prod.yml exec -T db \
    sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" ooo_db' < dev-dump.sql

# 3. Start the rest of the stack and bring the schema up to date
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec php bin/console doctrine:migrations:migrate --no-interaction
```

Restore before you migrate. The dump drops and recreates every table, including
`doctrine_migration_versions`, so migrations run first would be undone by the restore. Restoring
before the worker starts also keeps it from consuming queued dev messages.

The dump contains the dev fixture accounts, including `admin@whoisooo.app`, and every fixture
user has the password `123`. Delete those accounts or change their passwords before the site
is reachable.

In the dev stack, profile images sit in `app/public/uploads` on the host, not in a volume. Copy
them into the production volume with:

```bash
docker run --rm -v whoisooo-prod_uploads:/data -v "$PWD/app/public/uploads":/src:ro alpine \
    cp -a /src/. /data/
```
