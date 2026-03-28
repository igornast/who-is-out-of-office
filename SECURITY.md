# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| Latest (main) | Yes |

## Reporting a Vulnerability

If you discover a security vulnerability in Who's Out of Office, please report it responsibly — **do not open a public GitHub issue**.

**Contact:** Use the **Report a vulnerability** button on the [Security tab](https://github.com/igornast/who-is-out-of-office/security) (preferred), or email the maintainer directly via the contact listed on the GitHub profile.

Please include:
- A description of the vulnerability and its potential impact
- Steps to reproduce or a proof-of-concept
- Affected versions or components

You can expect an acknowledgement within **72 hours** and a resolution or mitigation plan within **14 days** depending on severity.

## Scope

The following are considered in scope:
- Authentication and authorization bypasses
- SQL injection, XSS, CSRF vulnerabilities
- Slack webhook signature bypass
- iCal feed access without valid secret
- Sensitive data exposure

Out of scope:
- Vulnerabilities in third-party dependencies (report upstream)
- Issues requiring physical access to the server
- Social engineering attacks

## Security Defaults

When deploying, make sure to:
- Set a strong `APP_SECRET` in your server environment (never use the dev default)
- Set a unique `ICAL_SECRET` to protect calendar feed URLs
- Store `SLACK_SIGNING_SECRET` and `SLACK_DSN` only in `.env.local` or server environment variables — never commit them
- Change or disable the default admin account created by fixtures (`admin@ooo.com`) before going live
