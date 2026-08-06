# Contributing to OverCustomise

Thank you for helping improve OverCustomise. Bug reports, documentation fixes, tests, and focused pull requests are welcome.

## Before you start

- Search existing issues and pull requests before opening a new one.
- Use GitHub Issues for reproducible bugs and feature proposals, not security reports or general store support.
- Report vulnerabilities according to [SECURITY.md](SECURITY.md).
- Keep proposals focused on WooCommerce product personalisation and print-production workflows.

For a substantial change, open a feature request first so the approach can be discussed before implementation work begins.

## Local setup

You need PHP 8.2+, Composer, Node.js (CI uses Node 22), WordPress 6.8+, and WooCommerce.

```bash
composer install
npm ci
npm run build
```

Install the repository as a WordPress plugin, activate WooCommerce, and then activate OverCustomise. Use a local or staging store with non-production customer data.

## Development workflow

1. Fork the repository and create a short-lived branch from the default branch.
2. Make one focused change and add or update tests where practical.
3. Do not commit credentials, customer artwork, production data, generated logs, or local configuration.
4. Run the relevant checks.
5. Open a pull request using the repository template and explain both the user impact and verification steps.

Useful commands:

```bash
npm run start             # Watch frontend assets
npm run build             # Production frontend build
npm run lint              # JavaScript and CSS linting
npm run test:js           # JavaScript unit tests
npm run test:performance  # Bundle-size budgets

composer lint:php         # PHP syntax checks
composer test             # PHP unit tests
composer phpcs            # WordPress coding standards
composer phpstan          # Static analysis
composer quality          # Combined PHP checks
```

Integration tests require `WP_TESTS_DIR`. Browser tests require `E2E_BASE_URL` and a prepared customisable product; see [README.md](README.md#integration-and-browser-tests).

## Coding expectations

- Follow the existing WordPress/PHP and JavaScript conventions.
- Sanitize input, escape output, check capabilities, and verify nonces at WordPress trust boundaries.
- Preserve WooCommerce HPOS and Cart/Checkout Blocks compatibility.
- Treat uploaded artwork, generated files, webhook destinations, and external API calls as security-sensitive.
- Keep source and compiled assets in sync when changing frontend code.
- Add changelog entries for user-visible behavior changes.
- Avoid unrelated formatting or refactoring in the same pull request.

## Pull requests

A strong pull request:

- explains the problem before the solution;
- links the relevant issue;
- describes compatibility or migration implications;
- includes automated tests or explains why they are not practical;
- includes screenshots or a short recording for interface changes;
- lists the exact commands used to verify the change; and
- contains no sensitive or personally identifiable data.

By contributing, you agree that your contribution is licensed under the project's [GPL-2.0-or-later license](LICENSE).
