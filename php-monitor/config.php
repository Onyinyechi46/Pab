<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

return [
    'network' => getenv('CARDANO_NETWORK') ?: 'mainnet',
    'script_address' => getenv('SCRIPT_ADDRESS') ?: '',
    'poll_limit' => (int) (getenv('BLOCKFROST_POLL_LIMIT') ?: 100),
    'blockfrost' => [
        'base_url' => getenv('BLOCKFROST_BASE_URL') ?: 'https://cardano-mainnet.blockfrost.io/api/v0',
        'api_key' => getenv('BLOCKFROST_API_KEY') ?: '',
        'timeout' => (int) (getenv('BLOCKFROST_TIMEOUT_SECONDS') ?: 30),
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'multisig_monitor',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host' => getenv('SMTP_HOST') ?: '',
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        'username' => getenv('SMTP_USER') ?: '',
        'password' => getenv('SMTP_PASS') ?: '',
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'from_email' => getenv('MAIL_FROM') ?: '',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Cardano Multisig Monitor',
        'recipients' => array_values(array_filter(array_map('trim', explode(',', getenv('MAIL_RECIPIENTS') ?: '')))),
    ],
];
