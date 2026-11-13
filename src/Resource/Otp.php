<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Client;
use MozeSMS\Exception\InvalidRequestException;
use MozeSMS\Exception\ValidationException;

final class Otp
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Envia um código OTP. Opções: sender_id, message, digits, expiry (s), max_attempts, ref_id, metadata, channel, email.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed> {session_id, phone, expires_at, expires_in}
     */
    public function send(string $phone, array $options = []): array
    {
        $phone = $this->client->validator->phone($phone);
        $this->client->validator->sender(isset($options['sender_id']) ? (string) $options['sender_id'] : null);
        if (isset($options['digits']) && ((int) $options['digits'] < 4 || (int) $options['digits'] > 10)) {
            throw new InvalidRequestException("'digits' tem de estar entre 4 e 10");
        }
        if (isset($options['expiry']) && (int) $options['expiry'] < 1) {
            throw new InvalidRequestException("'expiry' tem de ser positivo (segundos)");
        }
        return $this->client->request('POST', '/otp/send', [], ['phone' => $phone] + $options)['data'] ?? [];
    }

    /**
     * Verifica o código. Lança ValidationException (getErrorCode() = WRONG_CODE) se estiver errado;
     * outros códigos: EXPIRED, MAX_ATTEMPTS, ALREADY_VERIFIED, SESSION_NOT_FOUND.
     *
     * @return array<string,mixed>
     */
    public function verify(string $sessionId, string $code): array
    {
        $this->client->validator->id($sessionId, 'sessionId');
        if (trim($code) === '') {
            throw new InvalidRequestException("'code' é obrigatório");
        }
        return $this->client->request('POST', '/otp/verify', [], ['session_id' => $sessionId, 'code' => $code]);
    }

    /** Como verify(), mas devolve false quando o código está simplesmente errado. */
    public function check(string $sessionId, string $code): bool
    {
        try {
            $this->verify($sessionId, $code);
            return true;
        } catch (ValidationException $e) {
            if ($e->getErrorCode() === 'WRONG_CODE') {
                return false;
            }
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    public function status(string $sessionId): array
    {
        $this->client->validator->id($sessionId, 'sessionId');
        return $this->client->request('GET', '/otp/status/' . rawurlencode($sessionId));
    }

    /** @return array<string,mixed> */
    public function cancel(string $sessionId): array
    {
        $this->client->validator->id($sessionId, 'sessionId');
        return $this->client->request('POST', '/otp/cancel/' . rawurlencode($sessionId));
    }

    /**
     * @param array<string,mixed> $filters phone, status (pending|verified|expired|cancelled), ref_id, page, limit
     * @return array<string,mixed>
     */
    public function sessions(array $filters = []): array
    {
        return $this->client->request('GET', '/otp/sessions', $filters);
    }

    /** @return array<string,mixed> */
    public function stats(int $days = 7): array
    {
        return $this->client->request('GET', '/otp/stats', ['days' => max(1, min(90, $days))]);
    }
}
