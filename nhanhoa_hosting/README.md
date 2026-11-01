# Nhân Hòa Hosting (nhanhoa_hosting)

HostBill **Hosting** provisioning module that resells hosting through the Nhân Hòa
reseller API (`https://api.nhanhoa.com/`). HostBill does not manage any hosting
server itself — it places and tracks hosting **orders** in Nhân Hòa's inventory.

`class nhanhoa_hosting extends HostingModule`.

## The hard constraint: the API is tiny

The entire Nhân Hòa hosting API (https://docs.nhanhoa.com/#/hosting) is three
commands:

| cmd | formdata | purpose |
| --- | --- | --- |
| `register_hosting` | `hostingCode`, `domainNameList`, `hostingMonth` (≥6) | place a new hosting order |
| `renew_hosting` | `ord_id`, `hostingMonth` (≥6) | renew an existing order |
| `get_infohosting` | `hostingCode`, `domainName` | read `ord_id`, `ord_name`, `ord_end_time`, status `content` |

Plus list/account helpers: `service_code` (plan codes), `template_code` (OS
templates, VPS-oriented), `service_used`, `get_balance`.

**There is NO** suspend, unsuspend, terminate, change-plan or change-password
command, and **no control-panel SSO**. Those lifecycle methods are implemented
(HostBill calls them) but can only update HostBill-side state and raise a visible
notice that the action must be performed manually at `customer.nhanhoa.com`.

`register_hosting` is **asynchronous** — it returns only `{status,msg}`, not an
order id. `Create()` places the order then best-effort reads the id back with
`get_infohosting`; if it is not ready yet the service is still created and the id
is filled in later by the admin **Đồng bộ** button or a sync run.

## Connection (app) fields

| HostBill field | Nhân Hòa |
| --- | --- |
| Username | `auth-user` |
| Password | `auth-pwd` |
| Custom → Reseller ID | `auth-id` |
| Custom → Ghi log gỡ lỗi | debug log to `nhanhoa_hosting.log` |

## Product config options

| Option | Type | Source |
| --- | --- | --- |
| `hostingCode` | **loadable** | `service_code` (group_id=1) |
| `os_template` | **loadable** | `template_code` (VPS-oriented, optional) |
| `hosting_months` | text input | min 6 enforced |
| `control_panel_url` | text input | static link shown to client (no SSO) |

Loadable selects are prefilled server-side by the admin controller and also
answer the core `make=loadoptions` XHR (`ajax.options.tpl`). The currently saved
value is always kept selectable so a brief API outage never drops a selection.

## HostingModule method → Nhân Hòa cmd

| Method | cmd | Notes |
| --- | --- | --- |
| `testConnection` | `get_balance` | shows reseller balance |
| `Create` | `register_hosting` then `get_infohosting` | stores domain, code, ord_id, ord_name |
| `Suspend` / `Unsuspend` | — | no API; HostBill state only + manual notice |
| `Terminate` | — | no API; HostBill state only + manual notice |
| `ChangePackage` | — | updates stored plan code + manual notice |
| `ChangePassword` | — | stores new pass locally + manual notice |
| `Renew` | `renew_hosting` | admin action; needs ord_id |
| `getSynchInfo` / `Synchronize` | `get_infohosting` | refresh details + status |
| product form | `service_code`, `template_code` | loadable lists |

## Status mapping

`get_infohosting` returns Vietnamese status text (`content`), not a code:
`Đã kích hoạt` → Active, `tạm ngưng` → Suspended, `huỷ`/`hết hạn` → Terminated,
otherwise Pending.

## Files

```
class.nhanhoa_hosting.php                     main HostingModule
lib/class.nhanhoa_hosting_api.php             transport (adapted from nhanhoa_v3)
admin/class.nhanhoa_hosting_controller.php    product form + service page
user/class.nhanhoa_hosting_controller.php     client area
api/class.nhanhoa_hosting_apiroutes.php       REST (info, status)
api/nhanhoa_hosting_apiroutes.json            route manifest
templates/adminarea/{productconfig,ajax.options,account}.tpl
templates/clientarea/details.tpl
widgets/nhanhoa_hosting_status/                admin+client service summary widget
install.sql                                   optional order-map table
LICENSE                                       MIT
```

## Admin & client UI

- **Admin service page** (`admin/`, `templates/adminarea/account.tpl`) surfaces the
  live order status, plan code, order id/name, expiry and reseller balance, with
  **Đồng bộ** (get_infohosting) and **Gia hạn** (renew_hosting) actions and a link to
  the Nhan Hoa control panel. Suspend/terminate/change-plan are deliberately not
  offered here (no API — manual at `customer.nhanhoa.com`).
- **Client area** (`user/`, `templates/clientarea/details.tpl`) shows the domain,
  plan, live order status/expiry and the control-panel link (read-only).
- **Widget** (`widgets/nhanhoa_hosting_status/`, a `HostingWidget`) renders a compact
  summary panel on the service page — stored order identity, reseller balance
  (`get_balance`) and the services-in-use report (`service_used`), plus the panel
  link. It uses only stored details and throttle-cached lookups, so it never issues
  an uncached, rate-limited order call on render. It auto-attaches on (re)activation
  (`OPTION_UNIQUE_DEFAULT`).

## Install / activation

1. Copy the module to `includes/modules/Hosting/nhanhoa_hosting/`.
2. Activate it in **Settings → Modules** (creates the optional
   `hb_nhanhoa_hosting_orders` map table; a missing table is tolerated).
3. Create a **Server/App** with the reseller `auth-user` / `auth-pwd` / `auth-id`
   and run **Test connection** (`get_balance`).
4. On the product, **Connect with App**, pick the server, then load `hostingCode`
   (and optionally `os_template`) and set `hosting_months` (≥6).

No cron is required (hosting expiry is read live on demand / on sync).

## Provider-API limitations (important)

The Nhan Hoa reseller **hosting** API is order-based: `register_hosting`,
`renew_hosting`, `get_infohosting` only — plus the shared `service_code`,
`template_code`, `service_used`, `get_balance`. There is **no** suspend, unsuspend,
terminate, change-package, change-password or power/control command, and **no SSO**.
Those lifecycle methods are implemented so HostBill's own state stays consistent, but
they only update HostBill and raise a notice to act manually at
`customer.nhanhoa.com`.

## Changelog

- **1.0.0** — Initial release: provision/renew/sync hosting orders, loadable plan &
  template lists, admin + client UI, read-only REST routes, `service_used` reporting
  helper and the service-summary widget. MIT license + author headers added.

## License & author

MIT — see `LICENSE`. © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
