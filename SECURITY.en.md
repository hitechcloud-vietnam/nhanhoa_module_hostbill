# Security

[English](SECURITY.en.md) | [Tiếng Việt](SECURITY.md)

This document provides security configuration and reporting guidance for HiTechCloud Nhan Hoa Suite. It is not a claim that the integration, HostBill, servers, or third-party APIs have undergone an independent security audit.

## Secure configuration

- Use HTTPS endpoints published by the provider. Do not disable TLS certificate verification to work around connection errors.
- Store `auth-user`, `auth-pwd`, and `auth-id` in HostBill-managed Server/App connection fields. Never put credentials in source code, repositories, URLs, public tickets, screenshots, or shared logs.
- Use a reseller account with only the permissions required. Limit access to HostBill administration, servers, and databases according to staff responsibilities.
- Enable API debug logging only while diagnosing an issue. Even when a module masks the password field, logs may contain domains, order identifiers, response data, or personal information. Restrict access, set a retention period, and securely remove logs when finished.
- Some modules use short-lived caches for API reads. Apply appropriate access controls to the operating system's temporary directory and do not share caches or logs that contain service data.
- Back up the HostBill database and required settings, encrypt backups, restrict access, and test recovery. Do not include credentials in a database dump sent for support.
- Module REST routes require authentication and check service ownership in their handlers. Do not bypass HostBill authentication or expose handlers outside the platform's User API.
- After upgrading a module, recheck file permissions, Server/App configuration, route manifests, and related cron tasks. Test in staging before processing real orders.

## Data to protect

Depending on the module, data may include API credentials, domain names, registrant details, order identifiers, IP addresses, service status, DNS data, CSR/SAN values, or customer information. Share only the minimum needed to investigate an issue, and follow the operator's retention policy and applicable legal obligations.

## Reporting a vulnerability

Do not publicly disclose exploit details, customer data, credentials, or reproduction steps that could affect a live system. Send a private report to the repository maintainers through a security channel they have published. If no channel is listed, contact the repository administrators privately to identify one; do not attach secrets to a public issue or pull request.

A useful report includes the affected module/version, prerequisites, impact, minimal reproduction steps on an authorized environment, sanitized logs, and a way to follow up. Do not test a third party's accounts, APIs, or services without explicit authorization. Issues in HostBill or Nhan Hoa should be reported privately to the relevant owner.

## Scope

This repository covers the integration code and its documentation. HostBill infrastructure, PHP servers, Nhan Hoa APIs/portals, third-party libraries, and deployment configuration are managed separately; this integration does not replace their patching and monitoring processes.
