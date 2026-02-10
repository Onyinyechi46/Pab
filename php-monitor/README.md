# PHP Multisig Monitor (Standalone)

This folder adds an **independent** monitoring and analytics layer for a Cardano multisig dApp.
It does not modify or depend on the Lucid frontend runtime.

## Features

- Polls Blockfrost every 30 seconds for script-address transactions.
- Fetches UTxOs for each transaction.
- Decodes `inline_datum` CBOR and extracts signer list + threshold.
- Detects newly added signatures and stores them in MySQL.
- Sends SMTP email notifications for:
  - new signature detected
  - transaction execution/ready state reached
- Exports all tracked activity to `multisig_report.xlsx` via `export.php`.
- Works for Cardano mainnet or testnet by environment configuration.

## Directory

- `config.php` – environment-driven configuration.
- `database.php` – PDO layer and query helpers.
- `blockfrost.php` – Blockfrost HTTP client + inline datum CBOR decoder.
- `monitor.php` – polling worker (for cron).
- `notifier.php` – PHPMailer SMTP notifications.
- `export.php` – XLSX export endpoint using PhpSpreadsheet.
- `cron.sh` – launcher script for cron.
- `schema.sql` – MySQL schema.
- `.env.example` – environment variable template.
- `composer.json` – dependencies.

## Requirements

- PHP 8.1+
- MySQL 8+
- Composer
- Blockfrost project key

## Installation

1. Install dependencies:

```bash
cd /workspace/Pab/php-monitor
composer install --no-dev --optimize-autoloader
```

2. Create environment file:

```bash
cp .env.example .env
```

3. Update `.env` values (`SCRIPT_ADDRESS`, `BLOCKFROST_API_KEY`, DB and SMTP config).

4. Create database and schema:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS multisig_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p multisig_monitor < schema.sql
```

## SQL Schema

```sql
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tx_hash VARCHAR(255) NOT NULL,
    required_signatures INT NOT NULL,
    current_signatures INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tx_hash (tx_hash)
);

CREATE TABLE IF NOT EXISTS signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tx_hash VARCHAR(255) NOT NULL,
    signer_address VARCHAR(255) NOT NULL,
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tx_signer (tx_hash, signer_address),
    KEY idx_signatures_tx_hash (tx_hash)
);
```

## Running the Monitor

Manual run:

```bash
php /workspace/Pab/php-monitor/monitor.php
```

### Cron every 30 seconds

Cron does not support sub-minute scheduling directly, so run twice per minute:

```cron
* * * * * /workspace/Pab/php-monitor/cron.sh
* * * * * sleep 30; /workspace/Pab/php-monitor/cron.sh
```

## Export Endpoint

When served through Apache/Nginx + PHP-FPM, download report via:

```text
/php-monitor/export.php
```

This returns `multisig_report.xlsx` with columns:

1. Transaction Hash
2. Signer
3. Signature Count
4. Required Signatures
5. Status
6. Date

## Example `.env`

```dotenv
CARDANO_NETWORK=testnet
SCRIPT_ADDRESS=addr_test1...
BLOCKFROST_BASE_URL=https://cardano-testnet.blockfrost.io/api/v0
BLOCKFROST_API_KEY=your_blockfrost_project_id
BLOCKFROST_TIMEOUT_SECONDS=30
BLOCKFROST_POLL_LIMIT=100

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=multisig_monitor
DB_USER=root
DB_PASS=change-me

SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=monitor@example.com
SMTP_PASS=your-password
SMTP_ENCRYPTION=tls
MAIL_FROM=monitor@example.com
MAIL_FROM_NAME=Cardano Multisig Monitor
MAIL_RECIPIENTS=ops@example.com,security@example.com
```

## Notes

- Blockfrost endpoints used:
  - `GET /addresses/{scriptAddress}/transactions`
  - `GET /txs/{hash}/utxos`
- `monitor.php` uses a lock file to avoid overlapping runs.
- If your datum layout is custom, adjust `DatumDecoder::extractSignersAndThreshold()` in `blockfrost.php` to map your precise schema.
