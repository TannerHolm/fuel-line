# Fuel Line

System of record for Freedom Fuel's 90-day wholesale validation sprint. The build follows two
source documents — keep them authoritative:

- **Architecture spec**: https://claude.ai/code/artifact/0814e66f-dbe1-4d29-8258-df54f7867246
- **App Style Guide** (design canvas): https://claude.ai/design/p/285a4aa8-d9d1-4e2c-872b-e0e45ea77c2e

## Stack

Laravel 12 + Inertia v2 + Vue 3 (TypeScript) + Tailwind 3, PostgreSQL (`fuel_line` DB), served by
Herd at http://fuel-line.test. Tests run PHPUnit on sqlite `:memory:`.

- `npm run build` after frontend changes (no dev server assumed).
- `php artisan test` — full suite must stay green.
- Seed logins (local): `tanner@freedomfuel.us` / `chris@freedomfuel.us`, password `password`.
- Demo data seeds only in `APP_ENV=local` and only into an empty accounts table (`DemoDataSeeder`).

## Domain rules that must not drift

- **Events, not edits.** Check-ins, orders, samples, and stage changes are append-only rows.
  Stage history is logged automatically by `Account::booted()` — never insert `stage_transitions`
  by hand and never change a stage without going through the model.
- **Enums over free text** (`app/Enums/`). Pipeline stages, retailer types, lead sources, etc.
  KPI comparability depends on this.
- **KPIs are computed, never entered** — `App\Services\KpiService`, snapshotted nightly by
  `fuelline:compute-kpis`. The reorder-maturity window lives in `config/fuelline.php`
  (`maturity_days`), nowhere else.
- **Pricing tiers** live in the `pricing_tiers` table (seeded from the 90-Day Plan: $9.00/$8.50/$8.00
  at 25–49/50–99/100–249; 250+ quoted). The landing page, order flow, and any future Shopify sync
  must all read from this table.
- **Buyback gate**: a first (pilot) order cannot submit without a signed `buyback_agreements` row.
  Terms text + version hash come from `config/fuelline.php` — revising the text creates a new
  version without invalidating old signatures.
- **Roles**: `founder` / `retailer` / `investor` on `users.role`. Retailers are scoped to their one
  `account_id`; public registration always creates retailers. Founders are never self-registered:
  they come from `fuelline:make-founder` or from a founder using `/settings/users` (founder-only
  user admin — creates founder/retailer logins with a one-time set-password link, never a typed
  password; retailer logins there must pick an account). Investor access is signed-URL only
  (`/scorecard`) and has no user row.

## Design system (App Style Guide is law)

Dark surface is canonical — no light mode (`<html class="dark">` is forced; the appearance/theme
switcher was removed). Tokens are in `tailwind.config.js` (`ink`, `elevated`, `charcoal`, `ff.*`)
and component classes in `resources/css/app.css` (`ff-btn-*`, `ff-input`, `ff-input-sm`,
`ff-card`, `ff-label`, `ff-status`, `ff-notch`).

The starter kit's shadcn/radix UI kit, sidebar/header chrome, and stock Welcome/Dashboard pages
were deleted — every screen uses the FF primitives directly. `resources/js/components/` holds only
`FFModal.vue`; layouts are `FFLayout` (role-aware nav: founder tabs vs retailer portal),
`SettingsLayout`, and `AuthLayout`. radix-vue, lucide, headlessui, cva, clsx, tailwind-merge and
tailwindcss-animate were uninstalled — do not reintroduce them without reason.

**Gotcha Gothic ships broken vertical metrics** (declares 0.60em ascent; glyphs reach 0.83em), which
clipped text in fixed-height boxes. The `@font-face` carries `ascent-override: 95%` /
`descent-override: 25%` to correct it — don't remove those. Chrome also ignores `line-height` on
`<select>`, so compact controls must get real height (that's what `ff-input-sm` is for).

- Fonts: Norwester (structural/numeric AND all labels/buttons), Gotcha Gothic (prose),
  JetBrains Mono (IDs) — self-hosted in `public/fonts/`. The style guide originally assigned
  labels to Thedus Condensed Light, but at 10-11px with 0.18em tracking that face was too thin
  to read (worst on phones), so `ff-label`, `ff-label-sm`, `ff-status`, `ff-field-label` and the
  small buttons moved to Norwester at 11-13px with tighter tracking. Thedus is now unused.
  Norwester's metrics are sound (declares 0.88/0.17 against 0.80/0.10 of actual ink), so it
  needs no override — unlike Gotcha Gothic below.
- Mobile: `ff-input-sm` is compact only above 640px; on phones it returns to the 44px touch
  minimum. Horizontal scrollers use `.ff-rail` (momentum scroll, no scrollbar); the pipeline
  board additionally snaps with `scroll-pl-5` so a snapped column clears the page gutter.
  Form field pairs stack below `sm`, and the accounts table sheds columns rather than forcing
  a 720px sideways scroll.
- White is the primary button; the red gradient (`ff-btn-cta`) is reserved for the single commit
  action on a screen. Status is a 6px dot + condensed caps label, never a filled chip.
- One notched (`ff-notch`) hero plate per screen, max. Headlines uppercase. No emoji, no
  exclamation points. Errors state the fix, not the feeling.

## Production (Laravel Forge)

Server `harc-pro` (68.183.250.144, Ubuntu 24.04, PHP 8.4, **MySQL 8.4** — hence the portable SQL),
alongside voting.freedomfuel.us. Site id 3359355. **Live at https://wholesale.freedomfuel.us**
(Let's Encrypt, auto-renewing; the .on-forge.com domain still resolves as a fallback).

Note the cert covers the bare host only — the domain is set to "No redirect" because
`www.wholesale.freedomfuel.us` has no DNS record, and including it made Let's Encrypt's HTTP-01
validation fail for the whole certificate.

- Deploys from `TannerHolm/fuel-line:main`, push-to-deploy ON. Forge's stock zero-downtime script
  already runs composer install, npm run build, artisan optimize, storage:link, migrate --force.
- Database `fuel_line` (user `forge`). Scheduler installed (`schedule:run` every minute). Scheduled
  jobs: `fuelline:compute-kpis` 02:00, `fuelline:shopify-import --exclude=Testing` hourly (catch-up
  for what webhooks cannot see — new B2B companies have no order, so no webhook fires), and
  `fuelline:geocode` 02:30 to pin newly imported accounts.
- Webhooks must be REGISTERED, not just routed: `php artisan fuelline:shopify-webhooks` (idempotent;
  re-points stale subscriptions after a domain change, refuses a non-HTTPS APP_URL). Nothing fires
  until this is run — it was the reason orders/paid and orders/fulfilled were silently dead.
- Seeding in production: `php artisan db:seed --class=PricingTierSeeder --force` (tiers only).
  Founder logins come from `php artisan fuelline:make-founder`. Default mode PROMPTS for the
  password (run it from Forge's site terminal). For the non-interactive Commands box use
  `--link --name="..." --email=...`: it sets a random password nobody sees and prints a one-time
  password-reset URL (60 min, single use) for the founder to choose their own. Never type a
  password into the Commands box — it is stored in Forge's command log.
- Forge's Commands box eats backslashes and single quotes: use unqualified seeder class names.
- After editing the env in Forge, the config cache is stale until the next deploy (or
  `artisan optimize`) — the Cache toggle on the Environment page automates this.
- Running `artisan optimize` while the env/nginx are mid-change can leave bootstrap caches that
  500 every WEB request while CLI (`artisan about`) still works fine — that split is the tell.
  Fix: `php artisan optimize:clear`, then let the next deploy re-cache.

## Route map

- `/` landing (public pricing + signup CTA; authed users redirect by role)
- `/register` wholesale-partner signup (creates retailer user + pipeline account)
- `/portal`, `/portal/order`, `/portal/report` — retailer portal (auth + retailer)
- `/pipeline`, `/accounts*`, `/map`, `/kpis`, `/field` — founder app (auth + founder)
- `/accounts/import` — bulk paste/CSV import of prospective locations (founder). Client parses and
  previews; server normalizes enums/state names, skips name+city duplicates, imports the rest as
  Qualified Prospects and reports per-row results. Registered BEFORE `accounts/{account}` in
  web.php — keep it there or `import` gets swallowed by the model binding.
- `/settings/users` — founder-only user admin (see Roles above)
- `/scorecard` — investor view, `signed` URL required (generate from /kpis)

## Roadmap state (per spec §10)

Done: core CRM + pipeline board, check-in/sample/order quick-logs, KPI engine + nightly snapshots,
landing page + self-service ordering, buyback e-signature, retailer self-report portal, investor
scorecard, field screen + PWA manifest, territory map (`/map`: Leaflet + Esri dark tiles,
city-level pins via Nominatim geocoding — auto on account save, backfill with
`php artisan fuelline:geocode`; demo towns are pinned by the seeder without network calls).

Shopify (§9): built and tested against a faked API — `App\Services\ShopifyService` (GraphQL Admin
API, custom app token), `fuelline:shopify-import` (idempotent pull of wholesale customers + order
history; matches shopify_customer_id → email → name; infers stage, never regresses), HMAC-verified
webhooks at POST /webhooks/shopify (orders/paid, orders/fulfilled → go-live date + stage advance),
and outbound pushes (signup → customerCreate, portal order → draftOrderCreate, both afterResponse
no-ops until configured). Auth: Shopify retired the store-admin "Develop apps" route, so the app is a dev-dashboard app
using OAuth. Put SHOPIFY_CLIENT_ID / SHOPIFY_CLIENT_SECRET in .env, then a founder visits
/shopify/connect once — the offline access token is stored encrypted in `app_settings`
(`AppSetting`), never handled by a human. A legacy SHOPIFY_ADMIN_TOKEN in .env still wins if set.
Scopes: read_customers, write_customers, read_orders, write_draft_orders. Webhooks are signed with
the client secret and need a public URL (herd share / production); the importer does not.

CONNECTED (Aug 31 2026) to the live store. Canonical domain is `nrk5i9-j6.myshopify.com` —
`freedom-fuel-21612.myshopify.com` is an alias that OAuth will NOT match, so keep .env canonical.

Import selection: the store has no `wholesale` tag and almost no company fields, so
`--min-units=25` (the plan's wholesale minimum) is what separates retailers from DTC consumers.
Address fallback: wholesale buyers routinely leave the company/customer profile blank, so both
import paths fall back to the ORDER's shipping address for city/state/phone (this is what pins
MVP Distributing, whose company record has no address at all). Imported so far: MVP Distributing (Meridian ID),
Fasteddys (Meridian ID), Hammer Lane Market & Deli (North Bend WA).

KNOWN GAPS: (1) only the last 60 days of orders are visible — the app lacks `read_all_orders`, so
any pre-July-2026 history is missing and KPIs understate it. (2) Real orders were billed at
$10.00/unit (and $7.14 for MVP), i.e. the OLD price sheet, not the 90-Day Plan tiers
($9.00/$8.50/$8.00) seeded in `pricing_tiers` — reconcile before quoting anyone.

Not yet built: offline write queue for field mode (spec calls it non-optional before real field
use), 7/14/30-day check-in reminders (scheduler + Twilio SMS), Shopify B2B Companies support (Plus
only — importer currently uses tagged customers), case-study builder (§14), signed-agreement PDF
rendering to S3, magic-link retailer auth.
