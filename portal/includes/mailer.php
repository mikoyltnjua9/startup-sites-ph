<?php
// Minimal authenticated SMTP client — no external dependencies. Supports
// implicit SSL (port 465) and STARTTLS (port 587), AUTH LOGIN, and a single
// HTML-or-plain-text message to one recipient. Enough for this portal's
// one use case (sending a maintenance email) without vendoring a library.

declare(strict_types=1);

class SmtpMailer
{
    /** @var resource|null */
    private $socket = null;
    private string $lastError = '';

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * @return bool true on success, false on failure (call getLastError()).
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        try {
            $this->connect();
            $this->expect(220);

            $this->command('EHLO ' . (SMTP_HOST));
            $this->expect(250, true);

            if (SMTP_SECURE === 'tls') {
                $this->command('STARTTLS');
                $this->expect(220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Could not start TLS encryption.');
                }
                $this->command('EHLO ' . (SMTP_HOST));
                $this->expect(250, true);
            }

            $this->command('AUTH LOGIN');
            $this->expect(334);
            $this->command(base64_encode(SMTP_USER));
            $this->expect(334);
            $this->command(base64_encode(SMTP_PASS));
            $this->expect(235);

            $this->command('MAIL FROM:<' . SMTP_USER . '>');
            $this->expect(250);
            $this->command('RCPT TO:<' . $toEmail . '>');
            $this->expect(250);

            $this->command('DATA');
            $this->expect(354);

            $boundary = 'ssph-' . bin2hex(random_bytes(8));
            $headers = [
                'From: ' . $this->encodeHeader(SMTP_FROM_NAME) . ' <' . SMTP_USER . '>',
                'To: ' . $this->encodeHeader($toName) . ' <' . $toEmail . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'Date: ' . date('r'),
            ];

            // Dot-stuff any line that starts with a lone '.' per RFC 5321.
            $bodyLines = explode("\n", str_replace("\r\n", "\n", $htmlBody));
            $stuffed = array_map(fn($line) => str_starts_with($line, '.') ? '.' . $line : $line, $bodyLines);
            $data = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $stuffed) . "\r\n.";

            $this->command($data);
            $this->expect(250);

            $this->command('QUIT');
            fclose($this->socket);
            $this->socket = null;
            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            if ($this->socket) {
                fclose($this->socket);
                $this->socket = null;
            }
            return false;
        }
    }

    private function connect(): void
    {
        $host = SMTP_SECURE === 'ssl' ? 'ssl://' . SMTP_HOST : SMTP_HOST;
        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            $host . ':' . SMTP_PORT,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );
        if (!$this->socket) {
            throw new RuntimeException("Could not connect to mail server: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 15);
    }

    private function command(string $line): void
    {
        fwrite($this->socket, $line . "\r\n");
    }

    /**
     * Reads the SMTP response and throws unless it starts with the expected
     * code. Multi-line responses ("250-...") are read fully.
     */
    private function expect(int $code, bool $multiline = false): string
    {
        $response = '';
        do {
            $line = fgets($this->socket, 515);
            if ($line === false) {
                throw new RuntimeException('Connection to mail server was lost.');
            }
            $response .= $line;
            $continues = isset($line[3]) && $line[3] === '-';
        } while ($continues);

        $actual = (int) substr($response, 0, 3);
        if ($actual !== $code) {
            throw new RuntimeException("Mail server error: " . trim($response));
        }
        return $response;
    }

    private function encodeHeader(string $value): string
    {
        // Encode as UTF-8 base64 if it contains anything non-ASCII (e.g. a name).
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
