<?php

declare(strict_types=1);

final class BlockfrostClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function getAddressTransactions(string $address, int $count = 100): array
    {
        return $this->request(sprintf('/addresses/%s/transactions?order=desc&count=%d', $address, $count));
    }

    public function getTransactionUtxos(string $txHash): array
    {
        return $this->request(sprintf('/txs/%s/utxos', $txHash));
    }

    private function request(string $path): array
    {
        $url = rtrim($this->config['base_url'], '/') . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'project_id: ' . $this->config['api_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_FAILONERROR => false,
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Blockfrost request failed: ' . $error);
        }

        $decoded = json_decode($body, true);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : $body;
            throw new RuntimeException(sprintf('Blockfrost API error (%d): %s', $statusCode, $message));
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON received from Blockfrost.');
        }

        return $decoded;
    }
}

final class DatumDecoder
{
    public function decodeHex(string $hex): mixed
    {
        if ($hex === '' || (strlen($hex) % 2) !== 0) {
            return null;
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return null;
        }

        $offset = 0;
        return $this->decodeItem($bytes, $offset);
    }

    public function extractSignersAndThreshold(mixed $decodedDatum): array
    {
        $addresses = [];
        $threshold = null;

        $walker = function (mixed $node) use (&$walker, &$addresses, &$threshold): void {
            if (is_array($node)) {
                foreach ($node as $value) {
                    $walker($value);
                }
                return;
            }

            if (is_string($node)) {
                if (preg_match('/^(addr1|addr_test1)[a-z0-9]{20,}$/i', $node) === 1) {
                    $addresses[$node] = true;
                    return;
                }

                if (preg_match('/^[0-9a-f]{56}$/i', $node) === 1) {
                    $addresses['keyhash:' . strtolower($node)] = true;
                }

                return;
            }

            if (is_int($node) && $node > 0 && $node <= 255) {
                if ($threshold === null || $node > $threshold) {
                    $threshold = $node;
                }
            }
        };

        $walker($decodedDatum);

        $signers = array_keys($addresses);
        sort($signers);

        if ($threshold === null) {
            $threshold = count($signers);
        }

        return [
            'signers' => $signers,
            'threshold' => $threshold,
        ];
    }

    private function decodeItem(string $data, int &$offset): mixed
    {
        $initialByte = ord($data[$offset++]);
        $majorType = $initialByte >> 5;
        $additionalInfo = $initialByte & 31;

        return match ($majorType) {
            0 => $this->readUint($data, $offset, $additionalInfo),
            1 => -1 - $this->readUint($data, $offset, $additionalInfo),
            2 => bin2hex($this->readBytes($data, $offset, $additionalInfo)),
            3 => $this->readBytes($data, $offset, $additionalInfo),
            4 => $this->readArray($data, $offset, $additionalInfo),
            5 => $this->readMap($data, $offset, $additionalInfo),
            6 => $this->decodeItem($data, $offset),
            7 => $this->readSimple($data, $offset, $additionalInfo),
            default => null,
        };
    }

    private function readUint(string $data, int &$offset, int $additionalInfo): int
    {
        return match (true) {
            $additionalInfo < 24 => $additionalInfo,
            $additionalInfo === 24 => ord($data[$offset++]),
            $additionalInfo === 25 => unpack('n', $this->advance($data, $offset, 2))[1],
            $additionalInfo === 26 => unpack('N', $this->advance($data, $offset, 4))[1],
            default => (int) current(unpack('J', str_pad($this->advance($data, $offset, 8), 8, "\0", STR_PAD_LEFT))),
        };
    }

    private function readBytes(string $data, int &$offset, int $additionalInfo): string
    {
        $length = $this->readUint($data, $offset, $additionalInfo);
        return $this->advance($data, $offset, $length);
    }

    private function readArray(string $data, int &$offset, int $additionalInfo): array
    {
        $length = $this->readUint($data, $offset, $additionalInfo);
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = $this->decodeItem($data, $offset);
        }
        return $result;
    }

    private function readMap(string $data, int &$offset, int $additionalInfo): array
    {
        $length = $this->readUint($data, $offset, $additionalInfo);
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $key = $this->decodeItem($data, $offset);
            $value = $this->decodeItem($data, $offset);
            $result[is_scalar($key) ? (string) $key : 'key_' . $i] = $value;
        }
        return $result;
    }

    private function readSimple(string $data, int &$offset, int $additionalInfo): mixed
    {
        return match ($additionalInfo) {
            20 => false,
            21 => true,
            22, 23 => null,
            default => $additionalInfo,
        };
    }

    private function advance(string $data, int &$offset, int $length): string
    {
        $chunk = substr($data, $offset, $length);
        $offset += $length;
        return $chunk;
    }
}
