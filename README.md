# Novara Tickets

Two-way Mobile Money ticketing marketplace for **Uganda**: organizers publish events **and** services,
buyers pay with **MTN Mobile Money** from their phone, the platform takes a configurable commission, and
every ticket is a single-use QR pass validated server-side at the gate.

Built by **Novara Tech Africa** — *Everything by Novara.*

Stack: Laravel 13 · PHP 8.4+ · Blade + Tailwind Play CDN (no Node build step) · SQLite (Postgres-ready) · Docker on Render.

> PHP 8.4 is a hard requirement, not a preference: `symfony/http-foundation` 8.x declares
> `"php": ">=8.4.1"` and uses PHP 8.4 property hooks. The container image is `php:8.4-apache` and the
> boot script refuses to start on anything older.

---

## 1. Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open <http://127.0.0.1:8000>.

### Seeded accounts (password for all three: `password`)

| Role      | Email                         | Lands on      |
|-----------|-------------------------------|---------------|
| Admin     | `admin@novaratech.ug`         | `/admin`      |
| Organizer | `organizer@novaratech.ug`     | `/organizer`  |
| Buyer     | `buyer@novaratech.ug`         | `/my-tickets` |

Change the admin password before going live. On Render the administrator is provisioned from
`ADMIN_EMAIL` / `ADMIN_PASSWORD` on every boot, so those environment variables win over the seed.

### Mobile Money without live credentials

With no `PAYSTACK_SECRET_KEY` and no MTN subscription key the app binds the `DemoMomoGateway`, so the
whole flow is clickable locally: checkout → `/checkout/demo/{tx_ref}` → **Approve** → tickets issued,
wallet credited, commission split. **No real money moves.** The demo gateway is never bound on a
production deployment — see [Payment safety](#payment-safety).

---

## 2. Live Mobile Money (MTN)

The checkout talks to the **MTN MoMo Collections API**:

| | |
|---|---|
| Sandbox base URL | `https://sandbox.momodeveloper.mtn.com` |
| Production base URL | `https://momodeveloper.mtn.com` |
| Collect | `POST /collection/v1_0/request-payment` |
| Confirm | `GET /collection/v1_0/request-payment/{paymentId}` |
| Subscription header | `Ocp-Apim-Subscription-Key` |
| Auth header | `Authorization: Bearer {target_environment}` |

`https://mtn.com` is MTN's corporate website — it is **not** an API host.

### The buyer flow

1. `POST /checkout` creates the pending order (tickets are held for 10 minutes) and asks MTN to collect
   the money. The platform stores MTN's `paymentId` on the order as `gateway_reference`.
2. MTN pushes a USSD prompt to the buyer's phone. The buyer enters their PIN and approves. **There is
   no browser authorization page** — the buyer lands on `/checkout/prompt/{tx_ref}`, which polls
   `/checkout/status/{tx_ref}` until the charge lands.
3. MTN posts the result notification to `MTN_CALLBACK_URL` (`/api/v1/momo/webhook`).
4. The webhook controller confirms the charge **server to server** with
   `GET /collection/v1_0/request-payment/{paymentId}` and only then captures the payment: the order
   becomes `paid`, the QR tickets are issued, the organizer wallet is credited and the platform
   commission is booked.

### Environment

| Key | Purpose |
|---|---|
| `MOMO_GATEWAY` | `mtn` (default for Novara) or `paystack` |
| `MOMO_MODE` | Must be `live` in production; anything else disables payments |
| `MOMO_WEBHOOK_PROVIDERS` | Which providers the webhook accepts (`mtn`, `paystack`, `flutterwave`) |
| `MTN_BASE_URL` | Sandbox or production host, copied from the portal |
| `MTN_PRIMARY_KEY` | Primary `Ocp-Apim-Subscription-Key` |
| `MTN_SECONDARY_KEY` | Secondary key, used automatically when the primary is not entitled to the collections product |
| `MTN_TARGET_ENVIRONMENT` | The target environment string from the portal (e.g. `UgxAndMore`) |
| `MTN_CALLBACK_URL` | `https://online-ticket-c5mz.onrender.com/api/v1/momo/webhook` |
| `MTN_CURRENCY` | `UGX` |
| `MTN_PAYEE` | `Novara` |
| `MTN_WEBHOOK_SECRET` | Optional shared secret checked on top of the MTN confirmation |

**The subscription keys are secrets.** Keep them in the Render environment, never in the repository, and
regenerate them in the MTN portal if they are ever pasted into a chat, a ticket or a commit.

### MTN notifications are not signed

MTN result notifications carry no signature, so the platform never trusts one on its own:

* the `paymentId` in the notification must equal the `gateway_reference` the platform stored when it
  asked MTN to collect — a payment made against another merchant can never settle a Novara order;
* the charge is confirmed with a server-to-server status lookup that must report `SUCCESS`, the same
  `externalId` (the transaction reference) and the same amount as the order;
* the webhook is rate limited and the confirmation is idempotent, so a replayed notification settles
  nothing twice.

### Paystack Mobile Money instead

Set `MOMO_GATEWAY=paystack` and `PAYSTACK_SECRET_KEY=sk_live_…`. Paystack signs its callbacks
(`x-paystack-signature`, HMAC-SHA512 over the raw body), so the webhook middleware verifies the
signature directly. The buyer is redirected to Paystack's authorization page and returns to
`/checkout/return`.

---

## 3. Deploy to Render (Blueprint)

**This repository is already live-connected to Render.** Pushing to `main` triggers an automatic
rebuild and redeploy — no manual action is needed.

| | |
|---|---|
| Repository | `https://github.com/mwesigwaperez198/online.ticket` |
| Live app | `https://online-ticket-c5mz.onrender.com` |
| Health check | `https://online-ticket-c5mz.onrender.com/up` |
| Runtime | Docker (`Dockerfile` at the repository root), plan `starter` |
| Database | SQLite at `/var/data/database.sqlite` on a 1 GB disk mounted at `/var/data` |

### The `sync: false` values

| Key | Value |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` |
| `APP_URL` | `https://online-ticket-c5mz.onrender.com` |
| `ADMIN_EMAIL` | the administrator's login e-mail |
| `ADMIN_PASSWORD` | the administrator's password |
| `MTN_PRIMARY_KEY` / `MTN_SECONDARY_KEY` | the MTN subscription keys |
| `MTN_BASE_URL` / `MTN_TARGET_ENVIRONMENT` / `MTN_CALLBACK_URL` | the values from the MTN portal |
| `PAYSTACK_SECRET_KEY` / `PAYSTACK_PUBLIC_KEY` | only when `MOMO_GATEWAY=paystack` |

`APP_KEY` can also be generated by the container: the entrypoint generates one when it is empty, but
that value is not persisted — set it explicitly.

`ADMIN_EMAIL` / `ADMIN_PASSWORD` are applied by `php artisan ticketing:install` on **every** boot:
the account is created if it is missing and the password is re-synced when it changes. Leave
`ADMIN_PASSWORD` empty and the container generates a random one and prints it in the deploy log — the
platform never ships with the seeded `password` in production.

### Release checklist

```bash
php artisan ticketing:deploy-check          # environment, payments, routes, indexes, optimizers
php artisan ticketing:deploy-check --json   # the same checklist as JSON
```

The command fails with a non-zero exit code when a check fails, so it can be the last step of a
release.

### Deploy hygiene

- `docker/entrypoint.sh` validates the runtime (PHP ≥ 8.4.1, `pdo_sqlite`), provisions `APP_KEY`,
  migrates the database, installs the platform, then runs `storage:link`, `config:cache`,
  `route:cache` and `view:cache` on every boot. A failed migration stops the boot, so a broken
  release is reported by Render instead of silently serving errors.
- Apache is switched to `AllowOverride All`; the base image ships `AllowOverride None`, which
  silently disables Laravel's `public/.htaccess` and turns every non-root URL into a 404.
- `CACHE_STORE=database` and `SESSION_DRIVER=database` are required — the settings cache and the hold
  sweep lock live in the database.
- The SQLite file lives on the Render disk, so it survives restarts and deploys.

### Switching to Postgres (optional, env-only)

Set `DB_CONNECTION=pgsql` plus `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`, or
attach a Render Postgres and use its `DATABASE_URL`. Nothing in the application code changes.

---

## 4. How the money works

All amounts are **integer minor units**, 100 per major unit. UGX has no subunit, so the platform
stores UGX 120.00 as `12000` and sends `120` to the gateway.

| Step | Where |
|------|-------|
| Commission resolution | Organizer override → global `platform_commission_bps` → snapshot on the order as `commission_bps_applied` |
| Split at capture | `App\Services\PaymentProcessor::markPaid()` credits the organizer wallet (80% of the order) and books a `fee` ledger row (20%) |
| Refund | `App\Services\RefundService` reverses the organizer's share **and** the commission proportionally, and voids the tickets |
| Payout | Organizer requests → admin settles in `/admin` → `payout` debit row + `payouts` record |

Ledger tables: `wallets`, `wallet_transactions` (`sale`, `fee`, `refund`, `payout`, `adjustment`), `payouts`.

### Changing the commission

`/admin` → **Platform commission** panel. The percentage is always prompted before it is saved
(quick-set buttons for 5 / 10 / 15 / 20). It applies to **new** sales only — in-flight orders keep the
`commission_bps_applied` snapshot. A per-organizer override can be set in `/admin/approvals` (DA-VELA
runs at 0%).

---

## 5. Oversell protection

`App\Services\Inventory` never reads-then-writes capacity. It issues a conditional atomic update:

```sql
UPDATE ticket_types
SET held_count = held_count + :qty
WHERE id = :id AND capacity - sold_count - held_count >= :qty
```

Zero affected rows = sold out. On MySQL/Postgres the same path runs inside a `DB::transaction` with
`lockForUpdate()`. Pending orders hold inventory for 10 minutes (`HOLD_MINUTES`); expired holds are swept
lazily on the next request (Render has no cron), guarded by a cache lock.

---

## 6. Ticket lifecycle

1. Payment capture issues one ticket per seat with a unique `qr_code_hash` and a `TKT-XXXX` code.
2. `/organizer/scanner` validates the hash **server-side** (the QR image is never trusted) and returns
   `valid`, `already_used`, `refunded`, `void`, `wrong_event` or `invalid`. Every attempt is written to
   `ticket_scans`.
3. The scanner also supports camera scanning through `html5-qrcode`; pasting the code works offline.

---

## 7. Security

| Guard | Where |
|---|---|
| Checkout rate limit | 5/min per address, 10/min per account (`throttle:checkout`) |
| Login rate limit | 5/min per e-mail + address (`throttle:login`) |
| Webhook authentication | `app/Http/Middleware/VerifyWebhookSignature.php` — 401 on a bad signature, 503 when no provider secret is configured |
| Webhook idempotency | `webhook_events` unique on `(provider, event_id)` inside a transaction; a replay is acknowledged and settles nothing twice |
| Amount proof | The confirmed amount must match the order total before anything is captured |
| Payment safety | `MOMO_MODE` must be `live` in production; a missing key binds `UnavailableMomoGateway`, which authorises nothing. The demo gateway is never bound in production |
| Upload validation | JPEG / PNG / WebP only, sniffed from the file content, 2 MB maximum, platform-generated file name |
| Route isolation | Admin routes need `auth` + `role:admin`, organizer routes need `auth` + `role:organizer`, and every organizer query is scoped to the signed-in organizer |
| Indexes | `2026_02_15_000100_add_production_indexes.php` covers the marketplace read paths |

---

## 8. Swapping the payment gateway

`App\Services\PaymentGateway\Contracts\MomoGateway` is the only contract the checkout touches:

```php
public function initialize(array $payload): array;
public function verify(string $reference): ?array;
public function refund(string $gatewayReference, int $amountMinor, ?string $reason = null): bool;
public function verifyWebhookSignature(string $rawBody, array $headers): bool;
```

`MtnMomoGateway` (live MTN), `PaystackMomoGateway` (live Paystack), `DemoMomoGateway` (local) and
`UnavailableMomoGateway` (production guard) ship with the app. The binding lives in
`App\Providers\AppServiceProvider::register()`.

---

## 9. Test suite

```bash
php artisan test --compact
```

72 feature tests cover the checkout → hold → pay → ticket path, the MTN collection round trip, the
MTN server-to-server confirmation, the demo approval route, oversell rejection, webhook signature
verification and idempotency, commission changes, scanner validation, RBAC isolation, rate limits,
upload validation, the production indexes, hold expiry, service booking and the first-boot install
command.

---

## 10. Legacy DocuDrop app

The DocuDrop PHP app that sits **one directory above** this repository is a **separate, permanent
application** and is untouched by this build — it is not part of this repository and must never be
committed, because `config/db.php` holds live database credentials. Its hardening track (rotate the
live DB password, remove uploaded PHP/HTML, add a `download.php` gate plus `.htaccess`, CSRF
protection, IDOR/XSS fixes, and stopping `sw.js` from precaching authenticated pages) is a separate
piece of work — do not merge it into this codebase.
