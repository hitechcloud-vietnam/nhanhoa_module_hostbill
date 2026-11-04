# Nhan Hoa integration for HostBill

English | [Tiếng Việt](README.md)

This repository contains HostBill modules that connect to Nhan Hoa services through the provider's reseller APIs. It is distributed as the **HiTechCloud Nhan Hoa Suite**. HostBill does not replace Nhan Hoa's control panels, authoritative DNS, email platform, VPS infrastructure, or business processes.

## Modules

| Module | Scope | Documentation |
|---|---|---|
| `nhanhoa_cloud` | Order, synchronize, and renew Cloud VPS | [English](nhanhoa_cloud/README.md) · [Tiếng Việt](nhanhoa_cloud/README.vi.md) · [MIT](nhanhoa_cloud/LICENSE) |
| `nhanhoa_dns` | Manage ZoneDNS records | [English](nhanhoa_dns/README.md) · [Tiếng Việt](nhanhoa_dns/README.vi.md) · [MIT](nhanhoa_dns/LICENSE) |
| `nhanhoa_domain` | Register and manage domains, registrant details, and DNS | [English](nhanhoa_domain/README.md) · [Tiếng Việt](nhanhoa_domain/README.vi.md) · [MIT](nhanhoa_domain/LICENSE) |
| `nhanhoa_email` | Order, synchronize, and renew Email Hosting | [English](nhanhoa_email/README.md) · [Tiếng Việt](nhanhoa_email/README.vi.md) · [MIT](nhanhoa_email/LICENSE) |
| `nhanhoa_hosting` | Order, synchronize, and renew Hosting | [English](nhanhoa_hosting/README.md) · [Tiếng Việt](nhanhoa_hosting/README.vi.md) · [MIT](nhanhoa_hosting/LICENSE) |
| `nhanhoa_ssl` | Order, synchronize, and renew SSL certificates | [English](nhanhoa_ssl/README.md) · [Tiếng Việt](nhanhoa_ssl/README.vi.md) · [MIT](nhanhoa_ssl/LICENSE) |

Each module has its own installation steps, API configuration, service limitations, and license. Follow the documentation for the module you deploy; do not assume modules share the same HostBill category, configuration fields, or API capabilities.

## Getting started

1. Verify the HostBill and PHP requirements for your environment. This documentation does not claim a tested compatibility matrix for every HostBill/PHP release.
2. Back up the HostBill source and database. Test installation or upgrades in staging before production.
3. Install the module at the path specified in its README, enable it in HostBill, and configure reseller credentials in the Server/App settings.
4. Run **Test connection** when available, configure a product, and test a workflow with a test account or service.
5. Ensure HostBill cron runs for Email Hosting; other modules require cron only when their documentation says so.
6. Document how to synchronize, renew, and manually handle unsupported operations in the Nhan Hoa portal before offering the service.

Some provider API operations are asynchronous or unsupported. Verify order state in the Nhan Hoa portal; a HostBill status is not evidence that an unsupported provider-side operation occurred.

## Shared documentation

- [Changelog](CHANGELOG.md)
- [Brand guidance](BRAND.en.md) · [Tiếng Việt](BRAND.md)
- [Security and vulnerability reporting](SECURITY.en.md) · [Tiếng Việt](SECURITY.md)
- [Support and information to provide](SUPPORT.en.md) · [Tiếng Việt](SUPPORT.md)
- [Repository MIT license](LICENSE)

## Packaging and releases

Each module is released independently as a ZIP on GitHub Releases. The ZIP already contains its installation path; extract it from the HostBill root:

```bash
unzip nhanhoa_cloud-1.1.1.zip -d /path/to/hostbill
```

Files are installed under `includes/modules/Hosting/nhanhoa_cloud/`. `nhanhoa_domain` is installed under `includes/modules/Domain/nhanhoa_domain/`. Then activate the module in HostBill as described in its README.

Tags follow `nhanhoa_<module>-v<version>` and trigger creation of that module's ZIP, SHA-256 checksum, and GitHub Release. The **Publish module packages** workflow can also be run manually for a module and version. The module list and package paths are defined in [`scripts/package-module.sh`](scripts/package-module.sh) and the [workflow](.github/workflows/publish-modules.yml).

## Brand and support scope

**HiTechCloud Nhan Hoa Suite** is the integration suite's name. Nhan Hoa and HostBill are names or marks of their respective owners; references identify compatible services and do not imply endorsement, ownership, or an official partnership.

Repository support is limited to the integration code. Reseller accounts, invoices, live services, provider API incidents, and portal operations must be verified with Nhan Hoa. HostBill platform issues should be referred to the HostBill provider.

## License

The repository and each module are provided under the MIT License. See [LICENSE](LICENSE) and each module's LICENSE file for applicable terms and notices.
