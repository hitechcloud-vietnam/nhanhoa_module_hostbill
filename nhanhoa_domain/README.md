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

## Administrative divisions

The registrar's own `list_province` / `list_district` data still uses the
pre-merger province names. This module's mapping is the current one and must
not be synced to the registrar list:

| HostBill field | Registrar field | Meaning        |
|----------------|-----------------|----------------|
| `state`        | `ownerCity`     | tỉnh / thành phố |
| `city`         | `ownerDistrict` | quận / huyện     |
| `ward`         | `ownerTown`     | phường / xã      |

`getAdministrativeDivisions()` exposes the registrar list read-only, for
comparison. It never writes contact data.

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

Module `.php`/`.tpl` edits need `systemctl reload php-fpm-81` before the web
UI picks them up.
