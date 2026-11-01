# nhanhoa_dns

Nhan Hoa (Nhân Hòa) **ZoneDNS** module for HostBill. A greenfield `DNSModule`
built on the Nhan Hoa reseller ZoneDNS API (<https://api.nhanhoa.com/>,
documented at <https://docs.nhanhoa.com/zonedns.md>), modelled on the
`cloudns` / `webnic_dns` DNS modules.

Part of the **HiTechCloud Nhan Hoa Suite** (`nhanhoa_domain`, `nhanhoa_ssl`,
`nhanhoa_dns`).

## Overview

Manages DNS **records** for domains that belong to the reseller account. The
ZoneDNS surface is record-only and keyed by domain name: a zone exists
implicitly because the domain is registered to the reseller, so there is no
create/delete-zone, reverse-DNS or DNSSEC command. The module implements the
full `DNSModule` surface plus `ReverseDNS` and `DNSSECModule`, degrading the
unsupported parts gracefully.

## The Nhan Hoa ZoneDNS service

`POST https://api.nhanhoa.com/?act=<cmd>` with `auth-user`, `auth-pwd`,
`auth-id`, `cmd` and `formdata` (a urlencoded query string). Responses are
normalised to `['status','msg',...]` by `lib/class.nhanhoa_dns_api.php`; some
commands answer with a JSON **string** containing JSON and use `success:true`
instead of `status:"ok"` — the transport handles both. Read-only commands are
cached for 60 seconds to avoid the per-minute throttle.

## Connect / configuration

Configured as a HostBill **connected app / server**:

| App field | Maps to | Meaning |
|---|---|---|
| Username | `auth-user` | API username |
| Password | `auth-pwd` | API password |
| Reseller ID (ID API) | `auth-id` | `custom[auth_id]` |
| Debug log | — | `custom[debug_log]` → `nhanhoa_dns.log` |
| Default Nameservers | — | `custom[default_ns]` (fallback `ns1/ns2.nhanhoa.com`) |

Product options include `maxdomain`, `dns_template`, `ns1..ns4`, SOA settings and
the billing / zone-management visibility toggles (`templates/appconfig.tpl`,
`templates/productconfig.tpl`). **Test connection** calls `get_balance`.

## Command → method map

ZoneDNS is record-only; every call is keyed by `domain_name`.

| Nhan Hoa cmd | Module method | Purpose |
|---|---|---|
| `list_record` (`domain_name`) | `getZone()`, `rawRecords()`, `findRecord()` | read zone records |
| `add_record` (`domain_name`, `record_name`, `record_type`, `record_value`, `record_ttl`, `record_mx?`) | `addRecord()` | add a record |
| `edit_record` (`domain_name`, `record_id`, `record_type`, `record_value`, `record_ttl`, `record_mx?`) | `editRecord()` | edit a record (cannot rename) |
| `delete_record` (`domain_name`, `record_id`) | `deleteRecord()`, `editRecord()` | delete a record |
| `get_balance` | `testConnection()` | auth check |

Records carry a **native integer `record_id`**, so no synthesized ids are needed.
`edit_record` cannot rename a record, so a changed host is handled as
**delete + add**.

**Gap-fill audit (against `zonedns.md`):** every documented ZoneDNS command is
wired above. There is no zone create/list/delete, reverse-DNS or DNSSEC command
to add (see limitations).

## Admin UI

`admin/class.nhanhoa_dns_controller.php` extends `DNSTemplates_Controller`
(mirrors `cloudns`): connects the module to the configured app before each call,
wires the app/product configuration templates and the shared **DNS Templates**
tab. No extra admin tabs are added (ZoneDNS has no whitelabel / subscription /
premium surface).

## Client UI

`user/class.nhanhoa_dns_controller.php` extends `DNSClient_Controller`,
inheriting the generic DNS client area (zone list, record table,
add/edit/delete). The REST routes under `api/` expose a service's zones and
record management through the User API plugin; every route is `auth: true` and
re-checks ownership.

## Features

* Full record management: A, AAAA, CNAME, MX, TXT, NS, SRV, CAA.
* Native record ids; rename handled as delete + add.
* MX priority via `record_mx`; SRV packed into `record_value`; CAA formatted
  `flag tag "value"`.
* TTL clamped to the ZoneDNS range 300–86400.
* Configurable default nameservers; debug logging with `auth-pwd` masked.

## Provider-API limitations

The ZoneDNS API is **record-only**. It has **no** command for:

* **zone create / delete** — a zone follows the reseller domain, so `addZone()` /
  `deleteZone()` are best-effort local operations and `listZones()` uses
  HostBill's own DNS domains; `importZones()` returns nothing.
* **reverse DNS** (`.arpa`) — `getArpaZones()` returns `[]`.
* **DNSSEC** — `getZoneSecurity()`/`secureZone()`/… report "not available".

## Install / activation

1. Copy the module directory to `includes/modules/Hosting/nhanhoa_dns`.
2. Create a connected app/server with the reseller credentials (above) and,
   optionally, default nameservers.
3. Create a DNS product, connect it to this module, set limits/templates.
4. **Test connection** to verify.

## Changelog

* **1.0.0** — Nhan Hoa ZoneDNS module: full record management, admin + client
  controllers, templates, REST routes; `ReverseDNS` / `DNSSECModule` implemented
  as gracefully unsupported. This pass adds LICENSE, standardized author headers
  and this README.

## License & author

MIT — see [`LICENSE`](LICENSE).
Copyright (c) 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.

## Security, privacy & support

- Store `auth-user`, `auth-pwd` and `auth-id` only in the HostBill Server/App connection. Do not publish credentials or include them in screenshots or tickets.
- Debug logging is opt-in and masks the API password, but request/response content can include domain names and DNS values. Restrict log access, enable it only for diagnosis and remove retained logs under your policy.
- The REST routes require authentication and re-check service ownership. Review the HostBill User API exposure and service ownership configuration before enabling client access.
- Check Test connection and confirm the domain is available to the reseller account. TTL is constrained to 300–86400 seconds; record renaming is handled as delete plus add. The provider API does not create/delete zones and does not provide reverse DNS or DNSSEC.
- Before reporting a record issue, include the record type, sanitized name/value, requested TTL, timestamp and redacted API response. Never share credentials or private customer data. See [support](../SUPPORT.md) and [security reporting](../SECURITY.md).

## Brand & license

This module belongs to **HiTechCloud Nhan Hoa Suite**. Nhân Hòa and HostBill names belong to their respective owners; no endorsement is implied. See [brand guidance](../BRAND.md) and the [MIT license](LICENSE).
