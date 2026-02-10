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
