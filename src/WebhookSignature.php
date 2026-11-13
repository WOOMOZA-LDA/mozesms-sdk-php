<?php

declare(strict_types=1);

namespace MozeSMS;

use MozeSMS\Exception\SignatureVerificationException;

/**
 * Verifica webhooks recebidos. A MozeSMS envia o cabeçalho X-MozeSMS-Signature com o
 * HMAC-SHA256 (hex) do corpo EXATO do pedido, usando o secret do webhook, e X-MozeSMS-Event com o evento.
 *
 *     $event = \MozeSMS\WebhookSignature::parse($secret);   // lê php://input e os cabeçalhos
 */
final class WebhookSignature
{
    public static function sign(string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $rawBody, $secret);
    }

    public static function verify(string $rawBody, ?string $signature, string $secret): bool
    {
        if ($signature === null || $signature === '' || $secret === '') {
            return false;
        }
        return hash_equals(self::sign($rawBody, $secret), strtolower(trim($signature)));
    }

    /**
     * Valida e decodifica. Lança SignatureVerificationException se a assinatura não bater.
     *
     * @return array<string,mixed>
     */
    public static function constructEvent(string $rawBody, ?string $signature, string $secret): array
    {
        if (!self::verify($rawBody, $signature, $secret)) {
            throw new SignatureVerificationException('Assinatura de webhook inválida ou em falta');
        }
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new SignatureVerificationException('Corpo do webhook não é JSON válido');
        }
        return $data;
    }

    /**
     * Atalho para o endpoint que recebe o webhook: lê o corpo bruto e o cabeçalho.
     *
     * @return array<string,mixed>
     */
    public static function parse(string $secret): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false) {
            throw new SignatureVerificationException('Não foi possível ler o corpo do pedido');
        }
        $sig = $_SERVER['HTTP_X_MOZESMS_SIGNATURE'] ?? null;
        return self::constructEvent($raw, is_string($sig) ? $sig : null, $secret);
    }
}
