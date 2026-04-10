# LicenseSoft

A centralized PHP-based software licensing server for managing and verifying licenses across multiple tools. Designed to run on shared hosting (Hostinger) with no external dependencies.

Hosted at: `license.rntinfosec.in`

## Features

- **Per-customer licenses** with per-tool permissions and required expiry dates
- **Encrypted verification API** — AES-256-GCM encrypted payloads, per-tool keys, replay attack prevention
- **One installation binding** — each license locks to a single server (IP/domain) on first activation
- **Admin panel** — manage customers, tools, licenses, and activity logs
- **Tool management** — add tools and get unique AES keys to embed in your tools
- **License lifecycle** — create, revoke, extend expiry, transfer to new installation
- **Security hardened** — prepared statements, secure headers, brute force lockout, rate limiting, deserialization/file inclusion/race condition protections

## Tech Stack

- PHP 8+ (no Composer, no frameworks)
- MySQL via PDO
- Plain HTML/CSS/JS admin UI

## Design

See [`docs/superpowers/specs/2026-04-10-licensesoft-design.md`](docs/superpowers/specs/2026-04-10-licensesoft-design.md) for the full architecture, database schema, API specification, and security design.

## How It Works

1. Register a tool in the admin panel — get a unique AES-256 key
2. Create a license for a customer — assign tools and set expiry
3. Share the license key with the customer
4. Your tool calls `POST /api/verify` with an AES-encrypted payload containing the license key and server identifier
5. On first call, the license binds to that installation — subsequent calls verify the binding
