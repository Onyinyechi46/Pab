<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/blockfrost.php';
require_once __DIR__ . '/notifier.php';

$config = require __DIR__ . '/config.php';

if (empty($config['script_address']) || empty($config['blockfrost']['api_key'])) {
    throw new RuntimeException('SCRIPT_ADDRESS and BLOCKFROST_API_KEY are required.');
}

$lockFile = fopen(__DIR__ . '/monitor.lock', 'c+');
if ($lockFile === false || !flock($lockFile, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$database = new Database($config['db']);
$blockfrost = new BlockfrostClient($config['blockfrost']);
$decoder = new DatumDecoder();
$notifier = new Notifier($config['mail']);

try {
    $transactions = $blockfrost->getAddressTransactions($config['script_address'], $config['poll_limit']);

    foreach ($transactions as $tx) {
        $txHash = $tx['tx_hash'] ?? null;
        if (!$txHash) {
            continue;
        }

        $utxos = $blockfrost->getTransactionUtxos($txHash);
        $output = findScriptOutput($utxos['outputs'] ?? [], $config['script_address']);
        if ($output === null) {
            $previousStatus = $database->transactionStatus($txHash);
            $database->upsertTransaction($txHash, 0, 0, 'executed');
            if ($previousStatus !== 'executed') {
                $notifier->sendExecutionNotification($txHash, 0);
            }
            continue;
        }

        $inlineDatum = $output['inline_datum'] ?? '';
        $decodedDatum = $decoder->decodeHex($inlineDatum);
        $parsed = $decoder->extractSignersAndThreshold($decodedDatum);

        $requiredSignatures = max(1, (int) ($parsed['threshold'] ?? 0));
        $signers = $parsed['signers'] ?? [];
        $currentSignatures = count($signers);
        $status = $currentSignatures >= $requiredSignatures ? 'ready' : 'pending';

        $previousStatus = $database->transactionStatus($txHash);
        $database->upsertTransaction($txHash, $requiredSignatures, $currentSignatures, $status);

        foreach ($signers as $signer) {
            if ($database->signatureExists($txHash, $signer)) {
                continue;
            }

            $database->insertSignature($txHash, $signer);
            $notifier->sendSignatureNotification(
                $txHash,
                $signer,
                $currentSignatures,
                $requiredSignatures,
                $status
            );
        }

        if ($status === 'ready' && $previousStatus !== 'ready') {
            $notifier->sendExecutionNotification($txHash, $requiredSignatures);
        }
    }
} catch (Throwable $exception) {
    error_log('Monitor error: ' . $exception->getMessage());
}

flock($lockFile, LOCK_UN);
fclose($lockFile);

function findScriptOutput(array $outputs, string $scriptAddress): ?array
{
    foreach ($outputs as $output) {
        if (($output['address'] ?? '') === $scriptAddress) {
            return $output;
        }
    }

    return null;
}
