# nhanhoa_email — Nhan Hoa Email Hosting (HostBill Hosting module)

Resells Nhan Hoa (Nhân Hòa) **Email Hosting** through the reseller API at
`https://api.nhanhoa.com/`. This is a provisioning module, **not** a control-panel
module: Nhan Hoa runs the mail platform, HostBill sends provisioning intents and
reads the order back.

## What the reseller API supports

| HostBill operation | Nhan Hoa `cmd` | Notes |
| --- | --- | --- |
| `Create()` | `register_email` + `get_infoemail` | register then read back `ord_id` |
| Renew (admin/cron action) | `renew_email` | needs `ord_id`; min 6 months |
| `getSynchInfo()` / `Synchronize()` / cron | `get_infoemail` | refresh id/expiry/status |
| `testConnection()` | `get_balance` | also shows reseller balance |
| Product config plan list (loadable) | `service_code` (group_id 2) | `loadServiceCodes()` |

## What the API does NOT support — handled honestly, not faked

The reseller API has **no** command for suspend, unsuspend, terminate,
change-package, change-password or mailbox management. Those are driven from the
Nhan Hoa panel (`customer.nhanhoa.com`). The corresponding methods therefore:

- `Suspend()` / `Unsuspend()` — update **local** HostBill state + log + inform;
  they do **not** stop/resume mail at Nhan Hoa.
- `Terminate()` — marks local state; termination is effected by **not renewing**
  (service lapses at `ord_end_time`).
- `ChangePackage()` — stores the new plan code locally; real migration must be
  arranged with Nhan Hoa (it does not re-register, to avoid a duplicate paid order).
- `ChangePassword()` — reports that mailbox passwords are managed in the Nhan Hoa
  panel.

Each is logged via `accountLog` with a clear reason, so staff know what remains to
be done upstream.

## Connection fields

- **Username** → `auth-user`
- **Password** → `auth-pwd`
- **ID đại lý (auth-id)** (custom `auth_id`) → `auth-id`
- **Ghi log gỡ lỗi** (custom `debug_log`) → writes masked request/response to
  `nhanhoa_email.log`

## Product-config options

- `serviceCode` — **loadable** from `service_code` (group 2); falls back to the
  hand-typed `serviceCodeText` when the list cannot be fetched (hand-typed wins).
- `serviceMonth` — term in months; Nhan Hoa requires a minimum of 6 (values below 6
  are raised to 6).

## Service identity

One service = one domain. The order is keyed upstream by
`(serviceCode, domainName) -> ord_id`. Details are stored in `option1..option5`
(`ord_id`, `ord_name`, `end_time`, `domain`, `service_code`) and mirrored in
`hb_nhanhoa_email_orders` for lookup/audit.

## Status mapping (best-effort — FLAGGED)

`get_infoemail` reports service state in free-text `content`; only the value
`"Đã kích hoạt."` is documented. `mapStatus()` keyword-maps common Vietnamese/English
terms (active/pending/suspended/expired) and returns unknown text verbatim. Revisit
once more real `content` values are observed.

## Files

- `class.nhanhoa_email.php` — module (`nhanhoa_email extends HostingModule`)
- `lib/class.nhanhoa_email_api.php` — API transport (adapted from `nhanhoa_v3`)
- `admin/`, `user/`, `cron/` controllers
- `api/` REST routes (`info`, `sync`; read-only)
- `templates/adminarea/*`, `templates/clientarea/*`
- `install.sql` — `hb_nhanhoa_email_orders`
- `widgets/nhanhoa_email_status/` — admin+client service summary widget
- `LICENSE` — MIT

## REST routes (both `auth`, ownership-checked)

- `GET /service/@id/nhemail/info` — order summary + panel URL
- `GET /service/@id/nhemail/sync` — re-read live state (rate-limited)

## Admin & client UI

- **Admin service page** (`admin/`, `templates/adminarea/account.tpl`) shows the live
  order state read from Nhan Hoa and offers the two actions the API actually supports —
  **sync** (`get_infoemail`) and **renew** (`renew_email`); suspend/terminate/
  change-package are the core service buttons handled by the lifecycle methods
  (local-state-only, with a manual-action notice).
- **Client area** (`user/`, `templates/clientarea/details.tpl`) shows domain, plan
  (with the plan's mailbox/quota text), expiry and the Nhan Hoa mail-panel link.
- **Widget** (`widgets/nhanhoa_email_status/`, a `HostingWidget`) renders a compact
  summary on the service page — stored order summary (incl. expiry), reseller balance
  (`get_balance`) and the services-in-use report (`service_used`), plus the panel link.
  It uses only stored details and throttle-cached lookups. Auto-attaches on
  (re)activation (`OPTION_UNIQUE_DEFAULT`).

## Install / activation (incl. cron)

1. Copy to `includes/modules/Hosting/nhanhoa_email/` and activate it
   (creates `hb_nhanhoa_email_orders`).
2. Create a Server/App with the reseller `auth-user` / `auth-pwd` / custom
   `auth_id`, and run Test connection (`get_balance`).
3. Connect the product to the app and set the plan (loadable `serviceCode`, or
   hand-typed `serviceCodeText`) and `serviceMonth` (≥6).
4. **Cron:** `upgrade()` registers a daily `call_Daily` task (`hb_cron_tasks`,
   every 1200s) that walks active services and refreshes order state/expiry via
   `get_infoemail`, pausing ~1.2s between services to respect the one-lookup-a-minute
   throttle. Ensure the HostBill cron (`bin/cron.php`) runs.

## Provider-API limitations (important)

The Nhan Hoa reseller **email** API is order-based: `register_email`, `renew_email`,
`get_infoemail` only — plus the shared `service_code`, `service_used`, `get_balance`.
There is **no** suspend, unsuspend, terminate, change-package, change-password or
mailbox-management command, and **no SSO**. Those methods keep HostBill state
consistent and log/notify; the real action is done in the Nhan Hoa panel
(`customer.nhanhoa.com`), and termination is effected by not renewing (the service
lapses at `ord_end_time`).

## Changelog

- **1.0.0** — Initial release: register/renew/sync email-hosting orders, loadable
  plan list, admin + client UI, daily sync cron, read-only REST routes, `get_balance`
  / `service_used` helpers and the service-summary widget. MIT license + author
  headers added.

## License & author

MIT — see `LICENSE`. © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.

## Security, privacy & support

- Keep the reseller username, API password and reseller ID in the HostBill Server/App fields. Never include these values, mailbox passwords or customer data in a public report.
- Debug logging masks the API password but may contain domains, order identifiers and provider responses. Enable it temporarily, restrict access and remove logs when troubleshooting is complete.
- Confirm the product code and a term of at least 6 months. `register_email` may require a later sync before the order ID/status is populated; verify the order with the provider before taking billing or lifecycle action.
- The daily sync task depends on HostBill cron (`bin/cron.php`). If status or expiry stops refreshing, check that cron runs, review the task result and verify API availability. The API does not manage mailboxes, suspend mail service, change passwords or perform termination; use the Nhân Hòa panel for those actions.
- When asking for help, include module/HostBill/PHP versions, sanitized order/domain identifiers, cron timestamp and redacted error output. Follow [support](../SUPPORT.md) and [security reporting](../SECURITY.md); use the provider's channel for mailbox or upstream order issues.

## Brand & license

This module is part of **HiTechCloud Nhan Hoa Suite**. Nhân Hòa and HostBill are third-party names/trademarks; no endorsement is implied. See [brand guidance](../BRAND.md) and the [MIT license](LICENSE).
