<?php

declare(strict_types=1);

final class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function upsertTransaction(
        string $txHash,
        int $requiredSignatures,
        int $currentSignatures,
        string $status
    ): void {
        $sql = <<<'SQL'
            INSERT INTO transactions (tx_hash, required_signatures, current_signatures, status)
            VALUES (:tx_hash, :required_signatures, :current_signatures, :status)
            ON DUPLICATE KEY UPDATE
                required_signatures = VALUES(required_signatures),
                current_signatures = VALUES(current_signatures),
                status = VALUES(status)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tx_hash' => $txHash,
            ':required_signatures' => $requiredSignatures,
            ':current_signatures' => $currentSignatures,
            ':status' => $status,
        ]);
    }

    public function signatureExists(string $txHash, string $signerAddress): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM signatures WHERE tx_hash = :tx_hash AND signer_address = :signer_address LIMIT 1'
        );
        $stmt->execute([
            ':tx_hash' => $txHash,
            ':signer_address' => $signerAddress,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function insertSignature(string $txHash, string $signerAddress): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO signatures (tx_hash, signer_address) VALUES (:tx_hash, :signer_address)'
        );
        $stmt->execute([
            ':tx_hash' => $txHash,
            ':signer_address' => $signerAddress,
        ]);
    }

    public function transactionStatus(string $txHash): ?string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM transactions WHERE tx_hash = :tx_hash LIMIT 1');
        $stmt->execute([':tx_hash' => $txHash]);

        $result = $stmt->fetchColumn();
        return $result !== false ? (string) $result : null;
    }

    public function fetchExportRows(): array
    {
        $sql = <<<'SQL'
            SELECT
                t.tx_hash,
                COALESCE(s.signer_address, '') AS signer_address,
                t.current_signatures,
                t.required_signatures,
                t.status,
                COALESCE(s.signed_at, t.created_at) AS event_date
            FROM transactions t
            LEFT JOIN signatures s ON t.tx_hash = s.tx_hash
            ORDER BY event_date DESC, t.tx_hash ASC
        SQL;

        return $this->pdo->query($sql)->fetchAll();
    }
}
