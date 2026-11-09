# Security Policy

## Supported versions

Security fixes are applied to the current release line on `main`. Pre-1.0 releases are supported on a best-effort basis.

## Reporting a vulnerability

Do not open a public issue. Use the repository's private security advisory feature and include:

- the affected version or commit
- the steps to reproduce
- the impact you observed
- any proof of concept, logs, or screenshots
- whether the issue is already public

## Scope

Reports are welcome for:

- capability and nonce checks
- SQL injection and unsafe queries
- cross-site scripting and unsafe output
- cross-site request forgery
- authentication and API key handling
- file uploads and path handling
- server-side request forgery
- booking, payment, and attendee data exposure
- dependency and supply-chain issues

## What to expect

Maintainers will acknowledge the report, reproduce it, and work on a fix. You will be credited in the advisory unless you ask otherwise.

## No telemetry

ReEvents does not send telemetry, usage data, or license checks.
