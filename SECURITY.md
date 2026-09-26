# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 0.2.x | Yes |
| < 0.2 | No |

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

Prefer one of these channels:

1. **[GitHub Private Vulnerability Reporting](https://github.com/vayvolt/Nexis/security/advisories/new)** on this repository (preferred)
2. Contact via the Nexis portal: [nexis.vayvolt.de](https://nexis.vayvolt.de/) (Impressum / Kontakt)

Include:

- Affected Nexis version
- Steps to reproduce
- Impact (e.g. auth bypass, RCE, data exposure)
- Optional: patch or mitigation idea

You should receive an acknowledgement within a few business days. We will coordinate a fix and public disclosure after a release is available.

## Scope

In scope: Nexis core, first-party plugins under `plugins/nexis/`, and the default install/update paths.

Out of scope: third-party plugins from the directory, misconfigured hosting, and issues that only affect unsupported PHP/MariaDB versions.
