# Security policy

## Supported versions

Security fixes are applied to the latest version on the default branch. Older versions may not receive patches. The current project version is listed in `overcustomise.php` and `package.json`.

## Reporting a vulnerability

Please **do not open a public issue** for a suspected vulnerability.

Use GitHub's private **Report a vulnerability** option on the repository's Security tab. If that option is unavailable, contact a repository maintainer privately through the contact information on their GitHub profile before sharing technical details publicly.

Include, where possible:

- the affected version or commit;
- the prerequisites and configuration needed to reproduce it;
- clear reproduction steps or a minimal proof of concept;
- the likely impact;
- whether customer data or credentials may be exposed; and
- any suggested mitigation.

Do not access data that is not yours, disrupt a live store, perform denial-of-service testing, or retain customer information while researching a report.

Maintainers will aim to acknowledge a complete report, assess its impact, prepare a fix, and coordinate disclosure. Response times are best effort; the project does not currently offer a formal service-level agreement or bug bounty.

## Scope reminders

OverCustomise processes customer uploads, generates production files, calls optional external AI services, and can send webhooks. A secure deployment also depends on WordPress, WooCommerce, PHP, the web server, file permissions, cron, third-party providers, and site-specific configuration. Keep the complete stack patched and use staging data when testing.
