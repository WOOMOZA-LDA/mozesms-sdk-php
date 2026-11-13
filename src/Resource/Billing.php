<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;
use MozeSMS\Phone;

/** Recargas (topup), faturas e transações. */
final class Billing extends AbstractResource
{
    public const GATEWAYS = ['mpesa', 'emola', 'bank_transfer', 'paypal', 'txenka', 'manual'];

    /** Métodos de pagamento ativos para a conta (taxas, mínimo/máximo, instruções). @return array<string,mixed> */
    public function gateways(): array
    {
        return $this->body('GET', '/billing/gateways');
    }

    /**
     * Simula uma recarga: taxa, total a pagar e créditos que recebe. Não cria nada.
     *
     * @return array<string,mixed> {amount, gateway_fee, total_amount, credits_to_receive, ...}
     */
    public function calculate(float $amount, string $gateway): array
    {
        $this->checkAmount($amount, $gateway);
        return $this->item('POST', '/billing/calculate', [], ['amount' => $amount, 'gateway' => $gateway]);
    }

    /**
     * Cria uma recarga PENDENTE (não debita nada; o saldo só sobe quando o pagamento é confirmado).
     * mpesa/emola exigem $customerPhone. Conforme o método a resposta traz: payment_to (mpesa/emola),
     * bank_account (transferência), redirect_url (PayPal), checkout_url (Txenka) e sempre transaction_id, expires_at.
     * Para transferência bancária envie depois o comprovativo com uploadProof().
     *
     * Cada chamada cria uma transação nova: não repita um pedido que deu timeout sem antes ver transactions().
     *
     * @return array<string,mixed>
     */
    public function topup(float $amount, string $gateway, ?string $customerPhone = null): array
    {
        $this->checkAmount($amount, $gateway);
        $payload = ['amount' => $amount, 'gateway' => $gateway];
        if (in_array($gateway, ['mpesa', 'emola'], true) && ($customerPhone === null || trim($customerPhone) === '')) {
            throw new InvalidRequestException("'customerPhone' é obrigatório para $gateway");
        }
        if ($customerPhone !== null && trim($customerPhone) !== '') {
            $normalized = $this->client->validator->phone($customerPhone, 'customerPhone');
            if (in_array($gateway, ['mpesa', 'emola'], true) && !Phone::isMozambican($normalized)) {
                throw new InvalidRequestException("$gateway só aceita números de Moçambique (258)");
            }
            if (strlen($normalized) > 20) {
                throw new InvalidRequestException("'customerPhone' demasiado longo");
            }
            $payload['customer_phone'] = $normalized;
        }
        return $this->item('POST', '/billing/purchase', [], $payload);
    }

    /** Alias de topup(). @return array<string,mixed> */
    public function purchase(float $amount, string $gateway, ?string $customerPhone = null): array
    {
        return $this->topup($amount, $gateway, $customerPhone);
    }

    /** Confirma um pedido PayPal depois de o cliente aprovar. @return array<string,mixed> */
    public function paypalCapture(string $orderId): array
    {
        $this->required($orderId, 'order_id');
        return $this->body('POST', '/billing/paypal/capture', [], ['order_id' => $orderId]);
    }

    /** @param array{limit?:int,offset?:int,status?:string} $filters @return array<string,mixed> */
    public function transactions(array $filters = []): array
    {
        return $this->body('GET', '/billing/transactions', $filters);
    }

    /** @return array<string,mixed> */
    public function transaction(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/billing/transactions/$id");
    }

    /** Estado atual (útil para fazer polling depois de pagar). @return array<string,mixed> */
    public function transactionStatus(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/billing/transactions/$id/status");
    }

    /**
     * Comprovativo da transferência (JPG, PNG ou PDF, até 5 MB), a partir do disco.
     *
     * @return array<string,mixed>
     */
    public function uploadProof(int $transactionId, string $filePath): array
    {
        $this->id($transactionId, 'transactionId');
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            throw new InvalidRequestException('Comprovativo tem de ser JPG, PNG ou PDF');
        }
        $r = $this->client->upload('PUT', "/billing/transactions/$transactionId/proof", [], ['proof' => $filePath], 5 * 1024 * 1024);
        return isset($r['data']) && is_array($r['data']) ? $r['data'] : $r;
    }

    /** Reativa uma transação expirada. @return array<string,mixed> */
    public function reactivate(int $transactionId): array
    {
        $this->id($transactionId, 'transactionId');
        return $this->body('POST', "/billing/transactions/$transactionId/reactivate");
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function invoices(array $filters = []): array
    {
        return $this->body('GET', '/billing/invoices', $filters);
    }

    /** Fatura em binário (PDF). */
    public function downloadInvoice(int $id): string
    {
        $this->id($id);
        return $this->client->download("/billing/invoices/$id/download");
    }

    private function checkAmount(float $amount, string $gateway): void
    {
        $this->oneOf($gateway, self::GATEWAYS, 'gateway');
        if (!is_finite($amount) || $amount < 1 || $amount > 1e9) {
            throw new InvalidRequestException("'amount' tem de estar entre 1 e 1000000000 MZN");
        }
    }
}
