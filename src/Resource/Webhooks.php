<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Client;
use MozeSMS\Exception\InvalidRequestException;

/** Gestão dos webhooks (URLs que a MozeSMS chama com o estado das mensagens). */
final class Webhooks
{
    /** Eventos aceites. */
    public const EVENTS = [
        'sms.sent', 'sms.delivered', 'sms.undelivered', 'sms.failed', 'sms.expired',
        'sms.cancelled', 'sms.undeliverable', 'sms.rejected', 'sms.unknown',
    ];

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /** @return array<string,mixed> */
    public function list(): array
    {
        return $this->client->request('GET', '/webhooks');
    }

    /**
     * Sem $secret a API gera um (whsec_...) e devolve-o na resposta; guarde-o para verificar assinaturas.
     *
     * @param string[] $events ver Webhooks::EVENTS
     * @return array<string,mixed>
     */
    public function create(string $name, string $url, array $events, ?string $secret = null): array
    {
        if (trim($name) === '' || mb_strlen($name) > 100) {
            throw new InvalidRequestException("'name' tem de ter entre 1 e 100 caracteres");
        }
        if (!preg_match('#^https?://[^\s/]+#i', $url)) {
            throw new InvalidRequestException("'url' inválida: '$url'");
        }
        $this->checkEvents($events);
        $payload = ['name' => $name, 'url' => $url, 'events' => array_values($events)];
        if ($secret !== null) {
            $payload['secret'] = $secret;
        }
        return $this->client->request('POST', '/webhooks', [], $payload);
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $this->client->validator->positiveInt($id);
        return $this->client->request('GET', '/webhooks/' . $id);
    }

    /** @param array<string,mixed> $fields name, url, events, secret @return array<string,mixed> */
    public function update(int $id, array $fields): array
    {
        $this->client->validator->positiveInt($id);
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        if (isset($fields['events'])) {
            $this->checkEvents((array) $fields['events']);
        }
        return $this->client->request('PUT', '/webhooks/' . $id, [], $fields);
    }

    /** @return array<string,mixed> */
    public function toggle(int $id): array
    {
        $this->client->validator->positiveInt($id);
        return $this->client->request('POST', '/webhooks/' . $id . '/toggle');
    }

    /** Pede à API que envie um evento de teste. @return array<string,mixed> */
    public function test(int $id): array
    {
        $this->client->validator->positiveInt($id);
        return $this->client->request('POST', '/webhooks/' . $id . '/test');
    }

    /** @return array<string,mixed> */
    public function logs(int $id): array
    {
        $this->client->validator->positiveInt($id);
        return $this->client->request('GET', '/webhooks/' . $id . '/logs');
    }

    /** @param array<int,mixed> $events */
    private function checkEvents(array $events): void
    {
        if ($events === []) {
            throw new InvalidRequestException("'events' tem de ter pelo menos um evento");
        }
        $bad = array_values(array_diff(array_map('strval', $events), self::EVENTS));
        if ($bad) {
            throw new InvalidRequestException('Eventos inválidos: ' . implode(', ', $bad), $bad);
        }
    }
}
