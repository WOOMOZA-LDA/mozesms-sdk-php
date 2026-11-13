<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

final class Account extends AbstractResource
{
    /** @return array<string,mixed> {balance, currency, sms_price, estimated_sms, account:{name,email,company}} */
    public function balance(): array
    {
        return $this->client->request('GET', '/v1/account/balance')['data'] ?? [];
    }

    /** @return array<string,mixed> */
    public function profile(): array
    {
        return $this->item('GET', '/settings/profile');
    }

    /**
     * Atualiza dados do perfil: name, phone, timezone, language (pt|en). Palavra-passe e email não se mudam por aqui.
     *
     * @param array{name?:string,phone?:string,timezone?:string,language?:string} $fields
     * @return array<string,mixed>
     */
    public function updateProfile(array $fields): array
    {
        $allowed = ['name', 'phone', 'timezone', 'language'];
        $unknown = array_diff(array_keys($fields), $allowed);
        if ($unknown) {
            throw new InvalidRequestException('Campos não suportados: ' . implode(', ', $unknown) . '. Aceites: ' . implode(', ', $allowed));
        }
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        if (isset($fields['language']) && !in_array($fields['language'], ['pt', 'en'], true)) {
            throw new InvalidRequestException("'language' tem de ser pt ou en");
        }
        return $this->body('PUT', '/settings/profile', [], $fields);
    }

    /** Plano atual da conta. @return array<string,mixed> */
    public function plan(): array
    {
        return $this->item('GET', '/account/plan');
    }

    /** @return array<string,mixed> */
    public function tenantPlans(): array
    {
        return $this->item('GET', '/account/tenant/plans');
    }

    /** Informação da marca/tenant (white-label) da conta. @return array<string,mixed> */
    public function tenantInfo(): array
    {
        return $this->item('GET', '/tenant/info');
    }

    /** Preços do tenant. @return array<string,mixed> */
    public function pricing(): array
    {
        return $this->item('GET', '/tenant/pricing');
    }

    /** Estado público da API (não autenticado, bom para health checks). @return array<string,mixed> */
    public function apiStatus(): array
    {
        return $this->body('GET', '/status');
    }
}
