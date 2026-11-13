<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

/** Planos e faturação do WhatsApp. Cancelar uma subscrição faz-se no painel. */
final class WhatsAppBilling extends AbstractResource
{
    /** @return array<string,mixed> */
    public function plans(): array
    {
        return $this->body('GET', '/whatsapp/billing/plans');
    }

    /** Sessões/instâncias com plano. @return array<string,mixed> */
    public function instances(): array
    {
        return $this->body('GET', '/whatsapp/billing/instances');
    }

    /** Subscreve um plano para uma sessão (DEBITA o saldo). @return array<string,mixed> */
    public function subscribe(string $sessionId, int $planId, int $billingDay = 1): array
    {
        $this->required($sessionId, 'session_id');
        $this->id($planId, 'planId');
        if ($billingDay < 1 || $billingDay > 28) {
            throw new InvalidRequestException("'billingDay' tem de estar entre 1 e 28");
        }
        return $this->body('POST', '/whatsapp/billing/subscribe', [], ['session_id' => $sessionId, 'plan_id' => $planId, 'billing_day' => $billingDay]);
    }

    /** @return array<string,mixed> */
    public function changePlan(int $instanceId, int $planId): array
    {
        $this->id($instanceId, 'instanceId');
        $this->id($planId, 'planId');
        return $this->body('POST', "/whatsapp/billing/instances/$instanceId/change-plan", [], ['plan_id' => $planId]);
    }

    /** @param array<string,mixed> $config billing_day... @return array<string,mixed> */
    public function configure(int $instanceId, array $config): array
    {
        $this->id($instanceId, 'instanceId');
        if ($config === []) {
            throw new InvalidRequestException('Configuração vazia');
        }
        return $this->body('PATCH', "/whatsapp/billing/instances/$instanceId/config", [], $config);
    }

    /** Consumo; com $sessionId, só dessa sessão. @return array<string,mixed> */
    public function usage(?string $sessionId = null): array
    {
        return $this->body('GET', $sessionId === null ? '/whatsapp/billing/usage' : '/whatsapp/billing/sessions/' . rawurlencode($sessionId) . '/usage');
    }

    /** @return array<string,mixed> */
    public function invoices(?string $sessionId = null): array
    {
        return $this->body('GET', $sessionId === null ? '/whatsapp/billing/invoices' : '/whatsapp/billing/sessions/' . rawurlencode($sessionId) . '/invoices');
    }

    /** @return array<string,mixed> */
    public function charges(?string $sessionId = null): array
    {
        return $this->body('GET', $sessionId === null ? '/whatsapp/billing/charges' : '/whatsapp/billing/sessions/' . rawurlencode($sessionId) . '/charges');
    }
}
