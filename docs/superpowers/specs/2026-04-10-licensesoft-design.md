# LicenseSoft — Design Specification
**Date:** 2026-04-10
**Status:** Approved

---

## Overview

LicenseSoft is a centralized PHP-based licensing server hosted at `license.rntinfosec.in` (Hostinger shared hosting). It provides two surfaces:

1. **Admin Panel** — a login-protected web UI for managing customers, tools, licenses, and activity logs
2. **Verification API** — a single encrypted endpoint that external tools call to verify their license

No signup, no email sending. All communication is manual (admin copies and shares license keys). No external PHP dependencies — pure PHP 8+ and MySQL, deployable via FTP or hPanel.

---

## Architecture

```
┌─────────────────────────────────────────┐
│           LicenseSoft (PHP)             │
│                                         │
│  ┌──────────────┐  ┌─────────────────┐  │
│  │  Admin Panel │  │  Verification   │  │
│  │  (web UI)    │  │  API (REST)     │  │
│  └──────┬───────┘  └────────┬────────┘  │
│         └──────────┬────────┘           │
│              ┌─────▼──────┐             │
│              │  MySQL DB  │             │
│              └────────────┘             │
└─────────────────────────────────────────┘
          ▲                  ▲
          │                  │
   Admin (browser)    Your Tools (HTTP POST)
```

- Single PHP application, no framework
- MySQL database via PDO (prepared statements only)
- HTTPS enforced (Hostinger free SSL)

---

## Database Schema

### `admins`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| username | VARCHAR(100) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt |
| created_at | DATETIME | |

### `tools`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| name | VARCHAR(100) | Display name (e.g. "PortScanner") |
| slug | VARCHAR(100) UNIQUE | URL-safe identifier (e.g. "port-scanner") |
| aes_key | VARCHAR(64) | 32-byte hex AES-256 key, generated on creation |
| created_at | DATETIME | |

### `customers`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| name | VARCHAR(150) | Contact person name |
| email | VARCHAR(255) | |
| organisation_name | VARCHAR(255) | |
| org_domain | VARCHAR(255) | Informational only, not used for binding |
| created_at | DATETIME | |

### `licenses`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| customer_id | INT FK → customers.id | |
| license_key | CHAR(40) UNIQUE INDEXED | Random 40-char hex |
| installation_id | VARCHAR(255) NULL | NULL until first activation |
| expires_at | DATETIME | Required |
| status | ENUM('active','revoked') | Default: active |
| created_at | DATETIME | |

### `license_tools`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| license_id | INT FK → licenses.id | |
| tool_id | INT FK → tools.id | |

### `rate_limits`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| ip | VARCHAR(45) INDEXED | IPv4 or IPv6 |
| request_count | INT | Requests in current window |
| window_start | DATETIME | Start of current window |

Used only when `RATE_LIMIT_ENABLED = true`. Stale rows (window expired) are pruned on each request.

### `login_attempts`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| ip | VARCHAR(45) INDEXED | |
| attempts | INT | Failed attempt count |
| locked_until | DATETIME NULL | NULL if not locked |
| last_attempt | DATETIME | |

### `activity_logs`
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| license_id | INT FK → licenses.id | |
| installation_id | VARCHAR(255) | Caller's identifier |
| tool_slug | VARCHAR(100) | Which tool was checking |
| action | ENUM('activated','verified','failed','revoked') | |
| created_at | DATETIME | |

---

## Verification API

### Endpoint

```
POST https://license.rntinfosec.in/api/verify
Content-Type: application/json
```

### Request Format

```json
{
  "tool_slug": "port-scanner",
  "payload": "<base64(AES-256-GCM encrypted JSON)>"
}
```

`tool_slug` is plaintext — used by the server to look up the tool's AES key before decryption.

### Encrypted Payload (decrypted by server)

```json
{
  "license_key": "a3f9...40chars",
  "install_id":  "203.0.113.45",
  "ts":          1712345678
}
```

- `ts` is a Unix timestamp. Requests with `ts` outside ±5 minutes of server time are rejected (replay attack prevention).

### Verification Flow

```
1. Validate tool_slug exists → else 400
2. Decrypt payload using tool's AES key → else 400
3. Validate ts within ±5 min → else 401 (replay)
4. Look up license_key → not found: { valid: false, reason: "invalid_key" }
5. Check status = active → revoked: { valid: false, reason: "revoked" }
6. Check expires_at > NOW() → { valid: false, reason: "expired" }
7. Check tool is in license_tools → { valid: false, reason: "tool_not_licensed" }
8. Check installation_id:
   - NULL (first call): bind install_id, log "activated"
   - Matches: continue
   - Mismatch: { valid: false, reason: "install_mismatch" }
9. Log "verified"
10. Return encrypted: { valid: true, expires_at: "2026-12-31" }
```

### Response Format

All responses are AES-256-GCM encrypted with the same tool key, base64-encoded:

```json
{ "valid": true, "expires_at": "2026-12-31" }
```
or
```json
{ "valid": false, "reason": "expired" }
```

Reason values: `invalid_key`, `revoked`, `expired`, `tool_not_licensed`, `install_mismatch`, `replay_detected`

---

## Admin Panel

### Authentication
- Username + bcrypt password
- Session-based auth, 2-hour idle timeout
- Lock after 5 failed attempts for 15 minutes

### Pages

#### Dashboard
- Active license count
- Licenses expiring within 30 days
- Recent activity log entries

#### Tools
- List all tools with name, slug, and AES key (masked)
- Add tool: enter name → system generates slug + AES-256 key
  - Key shown **once** on creation with copy prompt — not recoverable after leaving page
- Delete tool (warns if active licenses reference it)

#### Customers
- List all customers (name, email, organisation, domain)
- Add customer: name, email, organisation name, org domain
- Edit customer details
- Delete customer (warns if active licenses exist)

#### Licenses
- List all licenses (customer, tools, expiry, status, bound installation_id)
- Create license: select customer, select tools (multi-select), set expiry date
- View license detail: shows license key (for copying to share with customer)
- Revoke license
- Extend expiry date
- Transfer: clears installation_id so the license re-activates on the next server that calls in

#### Activity Logs
- Filterable by: license, tool, action type, date range
- Shows: timestamp, tool slug, installation_id, action

---

## Security

### Encryption
- All API payloads encrypted with AES-256-GCM
- Per-tool unique 32-byte keys stored in `tools.aes_key`
- IV is randomly generated per request and prepended to the ciphertext before base64 encoding

### Rate Limiting
- Configurable on/off in `config.php`
- Default: 60 requests/min per IP
- Implemented via DB counter (no Redis required)

### Input Validation
- All inputs validated: type, length, format
- `tool_slug`: alphanumeric + hyphens, max 100 chars
- `install_id`: max 255 chars, stripped of control characters
- `license_key`: exactly 40 hex chars
- `ts`: must be a valid Unix integer

### Deserialization
- `unserialize()` never used — all data exchange via `json_decode()` with strict type checking

### File Inclusion
- No dynamic `include`/`require` — all file includes use hardcoded relative paths
- `open_basedir` restriction recommended in Hostinger PHP settings

### Race Conditions
- License activation uses a DB transaction with `SELECT ... FOR UPDATE` row lock
- Prevents double-binding on simultaneous first-call requests

### Secure Headers
Every response includes:
```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Content-Security-Policy: default-src 'self'
Referrer-Policy: no-referrer
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### SQL Injection
- PDO prepared statements used for all database queries

### Admin Brute Force
- 5 failed login attempts triggers a 15-minute lockout

### Known Trade-off
- The AES key must be embedded in each tool's source code. A determined attacker who can access the tool's source can extract the key. Obfuscating the tool's source mitigates this risk but cannot fully eliminate it.

---

## Configuration (`config.php`)

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'licensesoft');
define('DB_USER', '...');
define('DB_PASS', '...');
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_MAX', 60);       // requests
define('RATE_LIMIT_WINDOW', 60);    // seconds
define('TIMESTAMP_TOLERANCE', 300); // seconds (±5 min)
define('SESSION_TIMEOUT', 7200);    // 2 hours
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_DURATION', 900); // 15 min
```

---

## File Structure

```
/
├── config.php
├── index.php                  (admin login)
├── logout.php
├── api/
│   └── verify.php             (verification endpoint)
├── admin/
│   ├── dashboard.php
│   ├── tools.php
│   ├── customers.php
│   ├── licenses.php
│   └── logs.php
├── includes/
│   ├── db.php                 (PDO connection)
│   ├── auth.php               (session + lockout)
│   ├── crypto.php             (AES-256-GCM encrypt/decrypt)
│   ├── headers.php            (secure headers)
│   ├── rate_limit.php         (optional rate limiter)
│   └── validator.php          (input validation helpers)
└── assets/
    ├── style.css
    └── app.js
```
