# SentinelStack

A small self-hosted PHP app for tracking daily habits. Water, mindfulness, mood, sleep, movement, intentions. One account, one SQLite file.

## Features

- **Hydration** — log glasses (ml or oz), see 7-day and 30-day bar charts.
- **Intentions** — one-off tasks for today + recurring habits with daily or weekly cadence. Drag the list to reorder.
- **Mindfulness** — guided timer with three breath patterns (box, 4-7-8, equal).
- **Mood & gratitude** — one entry per day with optional note and one-line gratitude.
- **Movement** — eight curated routines, mark done for the day. Feeds the streak.
- **Sleep** — log hours and minutes; nightly entry.
- **Stats** — last 14 days of trends + a plain-English weekly review paragraph.
- **Notifications** — in-app bell + drawer with per-kind reminders (drinking, mindful, intentions, mood, sleep), opt-in email side-channel via SMTP, master "pause all" chips, and "Test bell / Test email" buttons so you can confirm the channels work without waiting for the cron.
- **Settings** — display name, timezone, theme, water unit + goal, password, reminder schedule, account deletion.

## Quick start

Requires PHP 8.0+ with `pdo_sqlite`, `mbstring`, `json`; Composer; SQLite.

```
composer install
cp .env.example .env
php database/seed.php
bin/dev
```

`bin/dev` boots the PHP dev server **and** the local SMTP catcher on `127.0.0.1:1025` in one shell so the **Test email** button on `/settings` has somewhere to land during development. Ctrl-C stops both. Captured mail is appended to `/tmp/smtp-catcher.log`. If you'd rather wire the two up yourself (e.g. under tmux), `php -S 127.0.0.1:8000 -t public public/index.php` and `php bin/dev-catcher.php` are the two commands it runs; either can be skipped. Without the catcher, the SMTP transport's `connect()` fails, Resend 422s on the dev From address, and PHP `mail()` has no MTA on a vanilla dev box — all three transports return false and the button shows a FAILED flash; the in-app bell drawer still works either way.
```

Open http://127.0.0.1:8000. First page is `/login`. Make an account, pick a timezone, you're in.

> Visit `http://127.0.0.1:8000`, not `http://localhost:8000`. The session cookie is bound to the host the browser actually requested; switching between the two mid-session desyncs "logged in" from "greeting visible" until you log out and back in.

## Configuration

Every knob in `.env`. Defaults are safe for local development.

| Variable | Default | What it does |
| --- | --- | --- |
| `APP_ENV` | `development` | `development` or `production`. |
| `APP_DEBUG` | `false` | Show PHP error output. |
| `APP_BASE_URL` | _(empty)_ | Fallback for redirects when `HTTP_HOST` is empty. |
| `DB_PATH` | `./data/sentinelstack.sqlite` | Where the SQLite database lives. |
| `SESSION_NAME` | `sentinelstack_session` | The session cookie name. |
| `SESSION_LIFETIME` | `0` | `0` = browser session, otherwise TTL in seconds. |
| `SESSION_SECURE` | `false` | Must be `true` when serving over HTTPS. |
| `CSRF_LIFETIME` | `14400` | CSRF token rotation period, in seconds (4 hours). |
| `LOGIN_MAX_ATTEMPTS` | `5` | Failed-login threshold per email before lockout. |
| `LOGIN_WINDOW_SECONDS` | `900` | Window the failed-login counter slides over. |
| `SEND_NOTIFICATIONS_EMAIL` | `false` | Server-wide opt-in for the email side-channel. Off by default so a self-hosted install without a working relay is silent instead of flooding the postfix queue. |
| `NOTIFICATION_FROM_EMAIL` | `sentinelstack@localhost` | The `From:` / envelope-sender address. Set to a real address on a domain you control — many SMTP relays reject `sentinelstack@localhost` for SPF/DKIM reasons. Resend will reject it until you've verified the domain. |
| `RESEND_API_KEY` | _(empty)_ | **Preferred** transport. If set (looks like `re_xxxx`), reminders go out via Resend's HTTP API; the SMTP vars below are ignored. No port-25 / STARTTLS / IP-reputation hassles and no MTA to babysit. |
| `NOTIFICATION_SMTP_HOST` | _(empty)_ | Raw SMTP transport — used only if `RESEND_API_KEY` is unset. If both this and `RESEND_API_KEY` are empty, falls back to PHP's `mail()`. |
| `NOTIFICATION_SMTP_PORT` | `587` | SMTP port. |
| `NOTIFICATION_SMTP_USER` / `NOTIFICATION_SMTP_PASS` | _(empty)_ | SMTP credentials. Leave blank for relays that don't require auth. |
| `NOTIFICATION_SMTP_ENCRYPTION` | `starttls` | `starttls` (port 587), `tls` (implicit TLS, port 465), or `none` (local dev only). |

`SESSION_SECURE=false` is required for plain HTTP. Set it to `true` behind NGINX or the ALB.

## Usage

Navigation is the sidebar on the left (or the bottom nav on mobile).

| Path | What it does |
| --- | --- |
| `/dashboard` | The Today page — affirmation, water ring, intentions count, streak tiles. |
| `/hydration` | Quick-add buttons, manual log, undo last entry, 7-day + 30-day charts. |
| `/todos` | Daily tasks and recurring habits. Drag to reorder. |
| `/mindfulness` | Pick a duration and a breath pattern, hit Begin. |
| `/mood` | Pick a mood emoji, write one gratitude line. One entry per day. |
| `/sleep` | Hours and minutes, save. |
| `/movement` | Eight routines. Mark done for the day. |
| `/stats` | Last 14 days of trends, weekly review. |
| `/settings` | Profile, theme, water goal, password, reminders, account deletion. |

The bell in the page header holds the day's notification inbox. The page polls every 60 seconds so new reminders show up without a hard reload.

## Notifications

In-app bell + drawer in the page header. Default cadence per kind:

| Kind | Default | Type |
| --- | --- | --- |
| Drinking | every 120 minutes | interval-based |
| Mindful | 09:00 | time-of-day |
| Intentions | 09:00 | time-of-day |
| Mood | 21:00 | time-of-day |
| Sleep | 22:30 | time-of-day |

Tweak any of these on `/settings` under the Reminders card. The master **pause all** chips at the top of that card silence every reminder for 1h, 3h, until evening, until bedtime, or until tomorrow morning. **Test bell** / **Test email** buttons at the bottom of the card fire an immediate in-app notification (and a real email, if email is enabled) so you can confirm the channel works without waiting for the cron.

To actually fire the reminders, run cron once a minute:

```cron
* * * * * /usr/bin/php /path/to/sentinelstack/database/notify.php >> /var/log/sentinelstack-notify.log 2>&1
```

The dispatcher is idempotent — a time-of-day reminder won't double-fire on the same local day, and the drinking interval won't re-fire inside its 120-minute window. All times are evaluated in each user's own timezone. A user who has paused all reminders is skipped entirely.

A time-of-day reminder has a **5-minute catch-up window** — if a cron tick lands within 5 minutes after the scheduled minute and today's reminder hasn't fired yet, it goes out (the dedup above stops any double-fire past that). Beyond 5 minutes, today's reminder is skipped; tomorrow's scheduled time will fire normally. This smooths over brief cron jitter or a server reboot without flooding the inbox if cron is offline for an extended period.

Set `SEND_NOTIFICATIONS_EMAIL=true` in `.env` if you want reminders as email too. The per-kind **Email me too** checkbox is a second opt-in; both must be true for an email to go out. Off by default so an instance without a working MTA isn't broken — the in-app drawer still works either way.

**Email provider options** (picked in this order, the first one with a value wins):

1. **Resend** — set `RESEND_API_KEY=re_xxxxxxxxxx`. Most reliable for self-hosted installs: no port-25 blocks, no STARTTLS negotiation, no IP-reputation game. Sign up at [resend.com](https://resend.com), grab a key, verify a domain in their dashboard, and set `NOTIFICATION_FROM_EMAIL` to an address on that domain. While your account is in onboarding mode, you can fall back to the `onboarding@resend.dev` sender that Resend provides for testing.
2. **Raw SMTP** — set `NOTIFICATION_SMTP_HOST=…`. Use the user/pass/encryption vars as usual. Tested relay shapes: Gmail (`smtp.gmail.com:587` STARTTLS), Mailgun, Postmark, SES, or any local catch-all on `127.0.0.1:1025` for dev.
3. **PHP `mail()`** — what happens if neither above is set. Requires a working local MTA, almost never the right answer on a shared host but works out of the box on a vanilla Linux box with postfix configured.

Whatever you use, the **Test email** button at the bottom of the Reminders card on `/settings` sends a real test message to your account's address and logs the outcome to the PHP error log (`[sentinelstack Smtp]` / `[sentinelstack Resend]`). Open `/var/log/php*-fpm.log` (or wherever your host logs PHP errors) to confirm the transport picked up.

Dry-run mode for testing the cron job without writing to the database:

```bash
php database/notify.php --dry-run
```

## Architecture

```
sentinelstack/
├── composer.json       PHP + extension deps only, no runtime libraries
├── .env.example        every env knob the app reads
├── public/             web root — what NGINX/Apache/Caddy serves
│   ├── index.php       front controller + static-asset guard for `php -S`
│   ├── .htaccess       mod_rewrite + sensitive-file deny list
│   └── assets/         css/ + js/, no build step
├── src/
│   ├── App/            framework primitives: Env, Database, Router, App,
│   │                   Session, Csrf, Response, View, Validator
│   ├── Models/         one class per table: User, WaterLog, Todo,
│   │                   MindfulnessSession, MoodEntry, MovementLog,
│   │                   SleepLog, Affirmation, RateLimit, DateUtil
│   └── Controllers/    one class per route group: Auth, Dashboard,
│                       Hydration, Todo, Mindfulness, Mood, Movement,
│                       Sleep, Stats, Settings, Api (AJAX), Health
├── config/             routines.php — curated movement-routine copy
├── templates/          plain-PHP templates wrapped in a layout shell
├── database/
│   ├── schema.sql      CREATE TABLE IF NOT EXISTS for everything
│   ├── seed.php        idempotent — schema + 62 affirmations
│   └── notify.php      CLI dispatcher for cron (idempotent, --dry-run mode)
└── data/               SQLite file lives here (gitignored, regenerated by seed)
```

The router in `src/App/Router.php` does method + path matching with `{param}` capture. Controllers are `[Class::class, method]` pairs declared in `src/App/App.php`. Models are static-class wrappers around prepared statements — no ORM, no migration tooling.

## Development

Lint every PHP file:

```
err=0; for f in $(find src templates public database config -type f -name '*.php'); do
  php -l "$f" 2>&1 | grep -q 'No syntax errors' || { err=$((err+1)); echo "FAIL $f"; }
done; echo "php-lint errors: $err"
```

Reseed the database cleanly:

```
rm -f data/sentinelstack.sqlite
php database/seed.php
```

The same lint runs on every push and pull request via `.github/workflows/ci.yml`, so a syntax error in any PHP file fails the build before it can land.

There's no test framework — `php -l` is the safety net. Every controller has an in-process HTTP path that you can hit from the browser to verify its behavior manually.

## Security

- Passwords hashed with Argon2id via PHP's `password_hash()` (no length cap, future-proof).
- Every mutating endpoint requires a valid CSRF token. The token lives in the session and rotates every `CSRF_LIFETIME` seconds.
- Sessions use `HttpOnly` and `SameSite=Lax` cookies. `SESSION_SECURE` flips on automatically behind HTTPS.
- `session_regenerate_id(true)` runs on login and registration so a stolen session cookie from before sign-in is useless.
- Login is rate-limited per email (default 5 failures in a 15-minute window). Register is rate-limited per IP so a stranger can't probe for registered emails by abusing the duplicate-email response.
- All SQL goes through PDO prepared statements with `EMULATE_PREPARES = false`. Foreign keys are on. Cascade deletes are configured.
- `public/.htaccess` denies direct access to `.env`, `.sqlite`, `.sql`, and `.md`.

## License

MIT.
