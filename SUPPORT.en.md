# Support

[English](SUPPORT.en.md) | [Tiếng Việt](SUPPORT.md)

## Scope

Repository support covers installation, configuration, and behavior of the six modules listed in the [README](README.en.md). It does not automatically include Nhan Hoa accounts or invoices, provider infrastructure or APIs, HostBill core bugs, PHP server configuration, or external services.

For operations not supported by a reseller API (for example control-panel actions, mailbox management, CSR/DCV, or DNSSEC, depending on the module), use the Nhan Hoa portal or support channel as described in that module's README. Do not treat a HostBill message or status as confirmation that the provider performed an operation.

## Before requesting help

1. Read the module README, especially its API limitations, configuration requirements, and troubleshooting guidance.
2. Confirm that the module, product, Server/App, and service identifiers use the intended configuration; check **Test connection** when available.
3. Record when the issue occurred, including timezone, the preceding action, and expected versus actual results.
4. Enable debug logging only for diagnosis and remove sensitive data before sharing logs.

## Request template

```text
Module and version:
HostBill and PHP version:
Environment: staging / production
Time and timezone:
Action or endpoint (no token/secret):
Expected result:
Actual result / sanitized error:
Reproduction steps on a test service:
Checks already performed:
Customer or order impact:
```

Do not send passwords, `auth-pwd`, API tokens, cookies/sessions, domain authorization codes, CSRs containing private keys, unnecessary personal data, database dumps, or raw logs. When provider verification is needed, use its private support channel.

## Escalation

- Module workflow or documentation issue: contact the repository maintainers through the project's official support channel.
- Order, balance, service state, portal data, or Nhan Hoa API issue: contact Nhan Hoa and share only authorized request/order identifiers.
- HostBill core, platform cron, or User API issue: contact the HostBill provider.
- Suspected vulnerability: follow [SECURITY.en.md](SECURITY.en.md); do not open a public issue.

The repository does not publish an email address, SLA, or response time. Do not assume a request has been received until the maintainer confirms through an official channel.
