# nhanhoa_domain

Nhan Hoa domain registrar module for HostBill. Successor to `nhanhoav2`;
both can be installed side by side and they share the contact mirror table
`hb_domain_contacts_nhanhoa`.

## Layout

```
class.nhanhoa_domain.php                 module class (DomainModule)
lib/class.nhanhoa_domain_api.php         HTTP transport, response normalisation, cache
lib/class.nhanhoa_domain_widget.php      shared base for the client-area widgets
orm/class.orm_domcontacts.php        Eloquent model for the contact mirror
admin/class.nhanhoa_domain_controller.php   admin panel (?cmd=nhanhoa_domain)
admin/*.tpl                          admin panel templates
widgets/nhanhoa_domain_*/                client-area widgets + templates
install.sql                          contact mirror table
```

## Registrar API

`POST https://api.nhanhoa.com/?act=<cmd>` with `auth-user`, `auth-pwd`,
`auth-id`, `cmd` and `formdata` (a urlencoded query string, not a nested
array). Documented at <https://docs.nhanhoa.com/>.

Two quirks the transport handles so nothing else has to:

* `list_record` answers with a JSON **string** containing JSON, and uses
  `success: true` instead of `status: "ok"`.
* The account is throttled per minute: repeat a lookup too quickly and every
  command returns `Thao tác quá nhanh vui lòng thử lại sau 1 phút!`. Read-only
  commands are cached for 60 seconds (`Nhanhoa_Domain_Api::CACHE_TTL`) in the
  system temp directory; throttle notices are never cached.

## Địa danh 2-tier (Vietnam 2025 administrative reform)

Vietnam's 2025 administrative reform (**sắp xếp đơn vị hành chính**) **abolished
the district (quận/huyện) tier**. Addresses are now two tiers, with the
ward/commune sitting **directly** under the province/city:

```
Tỉnh / Thành phố (province/city)  →  Xã / Phường (ward/commune)
```

### Field mapping (HostBill contact → Nhan Hoa registrar payload)

| HostBill field | Registrar field | Meaning          | Tier                    |
|----------------|-----------------|------------------|-------------------------|
| `state`        | `ownerCity`     | tỉnh / thành phố | **primary**             |
| `ward`         | `ownerTown`     | phường / xã      | **primary** (now)       |
| `city`         | `ownerDistrict` | quận / huyện     | **legacy / optional**   |

`makeContact()` implements the 2-tier model: **province + ward are primary**, and
a district is **no longer required** for a new registration.

> **Defensive `ownerDistrict`.** The registrar still documents `ownerDistrict` as
> a **required** field (`domainvn.md` marks it √; `ownerTown` is optional).
> Because the live API may still reject an empty `ownerDistrict`, it is populated
> defensively: the stored legacy district is sent when present, otherwise it
> **falls back to the ward value** so the required field is never empty. Whether
> the live API accepts a ward value — or an empty value — in the district slot
> **could not be verified offline**.

### How existing stored 3-tier addresses map

* A contact saved **before** the reform still carries its district in the
  HostBill `city` field; that value is kept and sent as `ownerDistrict`, so
  nothing breaks (**backward compatible**).
* **New** 2-tier contacts have an empty `city` (district); the ward is sent as
  both `ownerTown` and the defensive `ownerDistrict`.
* **Admins should re-verify stored registrant addresses after migration**: drop
  the obsolete district and keep the ward under its province/city.

### Division lookup

`getAdministrativeDivisions($level, $parent)` exposes the registrar division
registry **read-only** (it never writes contact data):

| `$level`   | cmd             | `$parent`    | Notes                                   |
|------------|-----------------|--------------|-----------------------------------------|
| `country`  | `list_country`  | —            |                                         |
| `province` | `list_province` | —            | tỉnh / thành                            |
| `district` | `list_district` | `cityId`     | legacy tier                             |
| `ward`     | `list_district` | `districtId` | **legacy**, chained under a district id |

The registrar exposes **no province→ward direct listing**, so `list_district` is
kept purely as a legacy lookup. `diadanh.md` documents the ward listing keyed by
`districtId`; the `cmd` printed in that doc section is `list_district` (a
copy/paste artifact — the distinguishing input is the parent id). The exact
ward-level cmd name **could not be verified offline**.

## Fixed relative to v2

* `LockDomain()` sent `ns1.photuesoftware.vn` in both nameserver slots.
* `check_input()` lowercased the key then compared it to `ownerName`, so
  registrant-name validation never ran. Re-enabled, with a wider
  organisation-keyword list so legitimate registrants (hợp tác xã, trường,
  viện, …) are not rejected now that the check is live.
* `getDomainInfo()`, `Unlock24h()`, `ResendVerify()`, `SkipVerifyEmail()` read
  `$this->name`, which is empty on client-area paths.
* `updateNameServers()` indexed a failed `getNameServers()` and returned false
  silently when nothing changed.
* `synchInfo()` built its return value from an undefined variable on the error
  path.
* `getContactInfo()` for international domains used `end(explode(...))` and
  indexed response keys that are not always present.
* cURL timeout 1000s → 60s; every response is now an array, so no caller can
  crash on `$result['status']` of `null`.
* Widgets: the status widget called the registrar on **every** client-area
  page render, templates ran `|escape` over an array and printed `Array`, and
  forms posted to an echoed `$_SERVER['REQUEST_URI']`.

## Added

* ZoneDNS record management via the `DomainModuleDNS` interface
  (`getDNSmanagement()` / `updateDNSManagement()`, backed by
  `list_record` / `add_record` / `edit_record` / `delete_record`).
  `updateDNSManagement()` reconciles: kept ids are edited, new rows added,
  missing ids deleted. A renamed record is delete + add, because
  `edit_record` cannot move a name.
* `checkBackorderStatus()` — declared as a command in v2, never implemented.
* `listUnsignedDomains()` — domains still missing their bản khai.
* `getAdministrativeDivisions()`.
* `debug_log` module setting: writes every request/response to
  `nhanhoa_domain.log` in this directory, with `auth-pwd` masked.

## Widgets

| Widget | `widgetdo` | Calls |
|---|---|---|
| `nhanhoa_domain_contract` | `nhanhoaDomainContract` | `Contract()` |
| `nhanhoa_domain_ekyc`     | `nhanhoaDomainEkyc`     | `getekyc()` |
| `nhanhoa_domain_status`   | `nhanhoaDomainStatus`   | `getStatusDomain()`, `Statusdomain()` |
| `nhanhoa_domain_profile`  | `nhanhoaDomainProfile`  | `getekycProfile()` |

Each acts only on its own token-checked POST — never on a plain render —
and registers with `options = 43` (`UNIQUE | DEFAULT_ON | SHOW_WHEN_PENDING |
SHOW_WHEN_PENDING_SYNC`). The v2 widgets registered with `options = 1` and
were never attached to a product in `hb_widgets`, which is why they never
appeared.

## Debugging

`tools/nhdev/` holds three CLI harnesses (bootstrap HostBill, write to stderr,
so run them as `php x.php … 2>&1 >/dev/null`):

```
probe.php  <Domain/module> <domain_id> <method>…   call module methods
raw.php    <cmd> [key=value]…                      call the registrar directly
widgetprobe.php <domain_id> [submit]               drive the widget controllers
```

Module `.php`/`.tpl` edits need a PHP-FPM reload before the web UI picks them up.

## Command → method map

Every registrar command the module uses, and the public method that issues it.
`.vn` commands get a `vn` suffix via `cmdFor()` (e.g. `register_domain` →
`register_domainvn`).

| Registrar cmd | Module method |
|---|---|
| `whois`, `whois_multi` | `checkAvailability()`, `lookupDomain()`, `lookupBulkDomains()` |
| `register_domain(vn)` | `Register()`, `ManualRegister()` |
| `transfer_domain(vn)` | `Transfer()` |
| `renew_domain(vn)` | `Renew()` |
| `backorder_domainvn` | `backorderDomainVN()` |
| `check_backorder_domainvn` | `checkBackorderStatus()` |
| `get_infodomain(vn)` | `getNameServers()`, `getContactInfo()` |
| `change_dnsdomain(vn)` | `updateNameServers()`, `LockDomain()` |
| `change_infodomain(vn)` | `updateContactInfo()` |
| `get_statusdomain(vn)` | `getStatusDomain()` |
| `domain_sync` | `synchInfo()` |
| `domain_tf_status` | `getRegistrarLock()` |
| `domain_lock` / `domain_unlock` | `updateRegistrarLock()` |
| `domain_authcode` | `getEppCode()` |
| `whois_protect` | `updateIDProtection()` |
| `info_control` | `getDomainInfo()` |
| `get_sign` / `get_sign_qt` | `Contract()` |
| `get_link_ekyc_tmvn` | `getekyc()` |
| `check_declaration` | `Statusdomain()` (see `domain_declaration.md`) |
| `unlock24h` | `Unlock24h()` |
| `resend_verify_domain` | `ResendVerify()` |
| `skip_verify_domain` | `SkipVerifyEmail()` |
| `list_domain` | `ListDomains()` |
| `list_domain_unsign` | `listUnsignedDomains()` |
| `domain_pricing` | `getDomainPrices()` |
| `list_country` / `list_province` / `list_district` | `getAdministrativeDivisions()` |
| `list_record` / `add_record` / `edit_record` / `delete_record` | `getDNSmanagement()` / `updateDNSManagement()` |
| `get_balance` | `testConnection()` |

**Gap-fill audit (against `docs.nhanhoa.com`):** every command documented for
`.vn`/international domains, ZoneDNS, pricing and the `diadanh.md` division
helpers is wired to a method above. `service_used` is a reseller reporting
helper and is intentionally not used by this registrar module.

## Install / activation

1. Copy the module directory to `includes/modules/Domain/nhanhoa_domain`.
2. In HostBill admin, activate the **Nhan Hoa Domain** module and enter the
   reseller **User Name**, **Password** and **User ID** (`auth-user` / `auth-pwd`
   / `auth-id`). Optionally enable **Write API calls to nhanhoa_domain.log**.
3. Use **Test connection** (calls `get_balance`) to verify credentials.
4. The contact-mirror table `hb_domain_contacts_nhanhoa` is created on demand
   (`install.sql` / `ensureContactsTable()`), shared with `nhanhoav2`.

## Changelog

* **1.0.0** — Nhan Hoa Domain registrar module (successor to `nhanhoav2`):
  hardened transport, re-enabled registrant validation, ZoneDNS record
  management, backorder status, unsigned-domain listing, client widgets.
* **Địa danh 2-tier update** — address mapping updated for Vietnam's 2025
  administrative reform (province + ward primary, district legacy/optional with a
  defensive `ownerDistrict`); `getAdministrativeDivisions()` gains a legacy
  `ward` level. Backward compatible with stored 3-tier contacts.

## License & author

MIT — see [`LICENSE`](LICENSE).
Copyright (c) 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
