<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class Notifier
{
    public function __construct(private readonly array $mailConfig)
    {
    }

    public function sendSignatureNotification(
        string $txHash,
        string $signer,
        int $currentSignatures,
        int $requiredSignatures,
        string $status
    ): void {
        $subject = sprintf('[Multisig] New signature for %s', $txHash);
        $body = sprintf(
            "A new signature has been detected.\n\nTransaction Hash: %s\nSigner: %s\nSignatures: %d/%d\nStatus: %s\nTimestamp: %s\n",
            $txHash,
            $signer,
            $currentSignatures,
            $requiredSignatures,
            $status,
            gmdate('Y-m-d H:i:s') . ' UTC'
        );

        $this->send($subject, $body);
    }

    public function sendExecutionNotification(string $txHash, int $requiredSignatures): void
    {
        $subject = sprintf('[Multisig] Transaction executed %s', $txHash);
        $body = sprintf(
            "Transaction has reached execution state.\n\nTransaction Hash: %s\nRequired Signatures: %d\nStatus: executed\nTimestamp: %s\n",
            $txHash,
            $requiredSignatures,
            gmdate('Y-m-d H:i:s') . ' UTC'
        );

        $this->send($subject, $body);
    }

    private function send(string $subject, string $body): void
    {
        if (empty($this->mailConfig['recipients'])) {
            return;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->mailConfig['host'];
            $mail->Port = $this->mailConfig['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->mailConfig['username'];
            $mail->Password = $this->mailConfig['password'];

            if (!empty($this->mailConfig['encryption'])) {
                $mail->SMTPSecure = $this->mailConfig['encryption'];
            }

            $mail->setFrom($this->mailConfig['from_email'], $this->mailConfig['from_name']);
            foreach ($this->mailConfig['recipients'] as $recipient) {
                $mail->addAddress($recipient);
            }

            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (Exception $e) {
            error_log('Notification failed: ' . $e->getMessage());
        }
    }
}
