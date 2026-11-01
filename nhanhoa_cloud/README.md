# Nhân Hòa Cloud VPS (nhanhoa_cloud)

HostBill **Hosting** provisioning module that resells **Cloud VPS** through the
Nhân Hòa reseller API (`https://api.nhanhoa.com/`). HostBill does not run any
hypervisor — it places and tracks Cloud VPS **orders** in Nhân Hòa's inventory and
links out to the Nhân Hòa panel for everything the order API cannot do.

`#[\AllowDynamicProperties] class nhanhoa_cloud extends HostingModule`.

## Nhan Hoa service covered

Cloud VPS (`service_code` group **3**, OS templates from `template_code`). One
HostBill service = one Cloud VPS order, resolved upstream by registrant email →
`cloud_id`, then `(vpsCode, cloud_id)` → order record.

## Connection (app) fields

| HostBill field | Nhân Hòa |
| --- | --- |
| Username | `auth-user` |
| Password | `auth-pwd` |
| Field 1 | `auth-id` (reseller account ID) |
| Custom → Debug log | writes request/response to `logs/nhanhoa_cloud.log` |

`testConnection()` calls `get_balance` and reports the reseller balance.

## Product config options

| Option | Type | Source / rule |
| --- | --- | --- |
| `vpscode` | **loadable** | `service_code` (group_id=3) via `getVpsPlans()` |
| `ostype` | select | `0` Linux / `1` Windows — picks which `template_code` list to load |
| `ostemplate` | **loadable** | `template_code` (type = `ostype`) via `getOsTemplates()` |
| `vpsmonth` | select | registration term; Nhân Hòa minimum is **3** (values <3 raised to 3) |

Stored per-account details: `option4` (domain/hostname), `cloud_id`, `ord_id`,
`ord_name`, `ord_end_time`, `vpscode`, `cloud_username`, `cloud_email`, `main_ip`,
`last_status`.

## Command → Nhân Hòa cmd

| Method / action | cmd | Notes |
| --- | --- | --- |
| `testConnection` | `get_balance` | reseller balance |
| `Create` | `register_cloud` → `list_cloud` → `get_infocloud` | register, then resolve `cloud_id` by email and read the order back |
| `renew` (admin) | `renew_cloud` | needs `ord_id`; min 3 months |
| `sync` (admin/client) | `get_infocloud` | refresh status/expiry, map to HostBill status |
| product form | `service_code` (3), `template_code` | loadable lists |
| reporting helper | `service_used` | reseller services-in-use (admin/widget) |
| `Suspend` / `Unsuspend` / `Terminate` | — | **no API**; HostBill billing state only + panel notice |
| `ChangePackage` / `ChangePassword` | — | **no API**; flagged, done in panel / OS |
| `client_power` / `client_reinstall` | — | **no API**; structured "managed in panel" notice |
| `client_console` | — | returns the Nhân Hòa panel deep link (actionable) |

`register_cloud` returns only `{status,msg}` (no id); `resolveOrder()` looks the new
order up with `list_cloud` by registrant email, stores `cloud_id`/IP, then pulls the
order with `get_infocloud`. Resolution is best-effort and never fails the order —
`cloud_id` can be reconciled later via **Sync**.

## Status mapping

`get_infocloud` returns Vietnamese `content` text; `mapStatus()` keyword-maps it:
`kích hoạt`/`hoạt động` → Active, `tạm ngưng`/`khóa` → Suspended,
`hủy`/`chấm dứt` → Terminated, `chờ`/`đang xử lý` → Pending, else unchanged.

## Admin & client UI

- **Admin service page** (`admin/class.nhanhoa_cloud_controller.php`,
  `admin/account.tpl`) surfaces status, plan, cloud/order id, IP and expiry, with
  **Sync status** (`get_infocloud`) and **Renew** (`renew_cloud`) actions (AJAX via
  `vpsdo`) and a **Nhan Hoa panel** link.
- **Client area** (`user/class.nhanhoa_cloud_controller.php`,
  `templates/clientarea/overview.tpl`) shows the status summary and the client VPS
  controls; power/reinstall/console surface a notice + panel deep link.
- **Widget** (`widgets/nhanhoa_cloud_status/`, a `HostingWidget`) renders a compact
  summary panel on the service page — status/plan/IP/expiry from the stored summary,
  reseller balance (`get_balance`) and the services-in-use report (`service_used`),
  plus the panel link. Uses only stored details and throttle-cached lookups; auto-
  attaches on (re)activation (`OPTION_UNIQUE_DEFAULT`).

## REST routes (all `auth`, ownership-checked)

| Route | Handler | Notes |
| --- | --- | --- |
| `GET /service/@id/nhanhoa_cloud/info` | `info` | stored identifiers, no API call |
| `GET /service/@id/nhanhoa_cloud/status` | `status` | live sync from Nhan Hoa |
| `POST /service/@id/nhanhoa_cloud/power/@action` | `power` | not supported — panel notice |
| `POST /service/@id/nhanhoa_cloud/reinstall` | `reinstall` | not supported — panel notice |
| `GET /service/@id/nhanhoa_cloud/console` | `console` | Nhan Hoa panel deep link |

Only whitelisted identifiers are returned — never the reseller credentials on the
server record. The manifest `api/nhanhoa_cloud_apiroutes.json` is the source of
truth (stable `uuid`s).

## Install / activation

1. Copy to `includes/modules/Hosting/nhanhoa_cloud/` and activate it in
   **Settings → Modules** (`install()` needs no table).
2. Create a Server/App with the reseller `auth-user` / `auth-pwd` and `auth-id`
   in **Field 1**; run Test connection (`get_balance`).
3. Connect the product to the app and set `vpscode`, `ostype`, `ostemplate` and
   `vpsmonth` (≥3).

No cron is required (status is refreshed on demand via Sync / the client status
action).

## Provider-API limitations (important)

The Nhan Hoa reseller **Cloud VPS** API is order-based: `register_cloud`,
`renew_cloud`, `list_cloud`, `get_infocloud` only — plus the shared `service_code`,
`template_code`, `service_used`, `get_balance`. There is **no** suspend, unsuspend,
terminate, change-plan (resize), change-password or power/reboot/reinstall/console
command, and **no SSO**. Those operations change only HostBill billing state (or
return a structured notice) and are performed by the end user in the Nhân Hòa panel
(`https://cloud.nhanhoa.com` / `customer.nhanhoa.com`). The control wiring (UI +
controller actions + REST routes) is in place so that, if Nhân Hòa ships control
commands later, only the matching method body needs filling in.

## Files

```
class.nhanhoa_cloud.php                        main HostingModule
lib/class.nhanhoa_cloud_api.php                transport (adapted from nhanhoa_v3)
admin/class.nhanhoa_cloud_controller.php       service page + product form
admin/{account,productconfig}.tpl              admin templates
user/class.nhanhoa_cloud_controller.php        client area
templates/clientarea/overview.tpl             client template
api/class.nhanhoa_cloud_apiroutes.php          REST (info, status, power, reinstall, console)
api/nhanhoa_cloud_apiroutes.json               route manifest
widgets/nhanhoa_cloud_status/                  admin+client service summary widget
LICENSE                                        MIT
```

## Changelog

- **1.0.2026-11-02** — Initial release: register/renew/sync Cloud VPS orders,
  loadable plan & OS-template lists, admin + client UI with panel deep links,
  client control routes (power/reinstall/console as panel-managed notices),
  `get_balance` / `service_used` helpers and the service-summary widget. MIT license
  + author headers added.

## License & author

MIT — see `LICENSE`. © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.

## Security, privacy & support

- Keep `auth-user`, `auth-pwd` and `auth-id` in the HostBill Server/App configuration; never put them in product descriptions, support screenshots or public logs.
- The optional API debug log masks credentials, but request/response data may still contain service identifiers, IP addresses or customer data. Enable it only while diagnosing, restrict file access and remove it according to your retention policy.
- Client REST handlers require authentication and verify that the requesting client owns the service. They return a limited service summary rather than reseller credentials.
- Check **Test connection** first. If creation returns before `cloud_id` is available, use **Sync status** after Nhân Hòa has completed order processing; this lookup is best-effort. Confirm the final order in the Nhân Hòa portal.
- Verify `vpscode`, OS type/template and a term of at least 3 months. Power, reinstall, console, suspend, terminate and resize/password changes are not performed by this API; use the provider panel where available.
- For an issue, include module/HostBill/PHP versions, the sanitized action and timestamp, product option names, and redacted error text. Do not send reseller credentials or customer secrets. General reporting and escalation guidance: [repository support](../SUPPORT.md) and [security reporting](../SECURITY.md).

## Brand & license

This module is part of **HiTechCloud Nhan Hoa Suite**, maintained under the attribution shown above. Nhân Hòa and HostBill are respective third-party names/trademarks; this integration does not imply endorsement. See [brand guidance](../BRAND.md) and the [MIT license](LICENSE).
