# nhanhoa_ssl

Nhan Hoa (Nhân Hòa) **SSL provisioning** module for HostBill. Implements the
HostBill `SSLModule` contract against the Nhan Hoa reseller API
(<https://api.nhanhoa.com/>, documented at <https://docs.nhanhoa.com/>).

Part of the **HiTechCloud Nhan Hoa Suite** (`nhanhoa_domain`, `nhanhoa_ssl`,
`nhanhoa_dns`).

## Overview

The module orders, syncs and renews Nhan Hoa SSL certificates. The Nhan Hoa SSL
API is intentionally minimal — it drives CSR collection, domain-control
validation and certificate delivery through Nhan Hoa's own customer portal and
e-mail flow — so this module implements the whole `SSLModule` surface (so
HostBill's UI and cron behave) but the operations the API cannot perform are
honest no-ops that record a clear message instead of pretending to succeed.

## The Nhan Hoa SSL service

`POST https://api.nhanhoa.com/?act=<cmd>` with `auth-user`, `auth-pwd`,
`auth-id`, `cmd` and `formdata` (a urlencoded query string, not a nested array).
JSON responses are normalised to `['status','msg',...]` by the transport
(`lib/class.nhanhoa_ssl_api.php`), which also serves read-only commands from a
60-second cache so the per-minute throttle (`Thao tác quá nhanh…`) does not lock
the reseller account out.

## Connect / configuration

Configured as a HostBill **connected app / server** (Settings → Apps, or the
product's *Connect with Module*):

| HostBill server field | Maps to | Meaning |
|---|---|---|
| Username | `auth-user` | API username |
| Password | `auth-pwd` | API password |
| Input 1 | `auth-id` | numeric reseller account id |
| Checkbox | — | write API calls to `nhanhoa_ssl.log` |

Product options:

* **SSL Product (Nhan Hoa service code)** — a loadable select populated by
  `listProducts()` (`service_code`, `group_id=4`).
* **Maximum SAN entries** — `0` disables the SAN form; `>0` offers SAN (sent
  comma-joined in `domainNameList`, see limitations).

**Test connection** calls `get_balance` (the cheapest authenticated read).

## Command → method map

| Nhan Hoa cmd | Module method | Purpose |
|---|---|---|
| `register_ssl` (`serviceCode`, `domainNameList`) | `Create()` | place an order |
| `renew_ssl` (`ord_id`) | `Renewal()` | renew an order |
| `get_infossl` (`serviceCode`, `domainName`) | `getSynchInfo()`, `refreshOrderInfo()`, `requireOrderId()` | status + `ord_id` + expiry |
| `service_code` (`group_id=4`) | `listProducts()`, `fetchSslProducts()` | SSL product list |
| `get_balance` | `testConnection()` | reseller balance / auth check |

**Gap-fill audit (against `ssl.md`, `service_code.md`, `balance.md`):** every
command the SSL API documents is wired above. `service_used` is a reseller
reporting helper and is intentionally not used.

## Admin UI

**Added in this pass** (the module was previously API-only — class + lib + api,
no admin controller/templates). An **admin management UI** now renders a
dedicated service page:

* `admin/class.nhanhoa_ssl_controller.php` — `accountdetails()` connects the
  module and renders the page; handles `&sync` (→ `getSynchInfo()` + persist) and
  `&renew` (→ `Renewal()` + persist).
* `admin/myadminarea.tpl` — shows **common name, product (service code), Nhan Hoa
  order id, order name, status, vendor status, expiry and SAN**, with **Sync
  status** and **Renew** buttons wired to the existing methods, plus a note that
  CSR/DCV/reissue/cancel/download are handled in the Nhan Hoa portal.
* A read-only `CertDetails()` accessor was added to the module class to feed the
  template from persisted service details (no API call on view).

> Chosen branch: an **admin controller + service-page template** (not a
> dashboard widget), matching the `comodossl` / `daousign_ssl_v3` SSL modules.

## Client UI

Standard HostBill SSL client area (certificate status). The REST routes under
`api/` expose the client-facing SSL API (status sync + the nominal DCV method
list) through the User API plugin; every route is `auth: true` and re-checks
ownership via `serviceBelongsToClient()`.

## Features

* Order placement, status synchronisation and renewal.
* Free-text (Vietnamese) vendor status mapped to HostBill SSL statuses by
  keyword, with expiry as a secondary signal (`mapVendorStatus()`).
* Issued-certificate notification on transition; expiry returned as `next_due`.
* Loadable product list; optional SAN; debug logging with `auth-pwd` masked.

## Provider-API limitations

The Nhan Hoa SSL API exposes **only** `register_ssl`, `renew_ssl`, `get_infossl`
plus `service_code` / `get_balance`. It has **no** API for:

* CSR generation/submission,
* DCV (email / HTTP / DNS) — no approver-email handling or resend,
* certificate reissue,
* order cancel / refund,
* certificate download.

These are handled in the **Nhan Hoa customer portal** (out of band). The module
advertises the conventional DCV method list for display only; the
change/resend/detail operations, `Reissue()`, `Terminate()` and `CertDownload()`
are honest no-ops that record the limitation. Multi-domain (SAN) is sent
comma-joined in the single documented `domainNameList` field — an assumption
pending confirmation from Nhan Hoa.

## Install / activation

1. Copy the module directory to `includes/modules/Hosting/nhanhoa_ssl`.
2. Create a connected app/server with the reseller credentials (above).
3. Create an SSL product, connect it to this module, pick the **service code**.
4. **Test connection** to verify, then order through the normal SSL flow.

## Changelog

* **1.0.0** — Nhan Hoa SSL provisioning module: order / sync / renew, product
  list, status mapping, REST routes. This pass adds the admin management UI
  (controller + service-page template + `CertDetails()`), LICENSE, standardized
  author headers and this README.

## License & author

MIT — see [`LICENSE`](LICENSE).
Copyright (c) 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
