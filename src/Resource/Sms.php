<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Client;
use MozeSMS\Exception\InvalidRequestException;
use MozeSMS\Validator;

final class Sms
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Envia um SMS. Números moçambicanos: 9 dígitos (84xxxxxxx) ou com 258.
     *
     * Atenção: o resultado pode ter status "falhado" sem exceção só se a API devolver 2xx; falhas do gateway
     * (502) lançam ServerException e o SMS já pode estar cobrado/registado: veja o erro com getBody().
     *
     * @param array<string,mixed> $extra provider, device_id, sim_slot
     * @return array<string,mixed> {id, phone, status, parts, cost, remaining_balance, ...}
     */
    public function send(string $phone, string $message, ?string $senderId = null, array $extra = []): array
    {
        $v = $this->client->validator;
        $phone = $v->phone($phone);
        $v->message($message);
        $v->sender($senderId);
        $payload = ['phone' => $phone, 'message' => $message] + $extra;
        if ($senderId !== null) {
            $payload['sender_id'] = $senderId;
        }
        return $this->client->request('POST', '/sms/send', [], $payload)['data'] ?? [];
    }

    /**
     * Um texto para muitos números (até 100000). {{nome}}, {{telefone}} e {{empresa}} são personalizados.
     * Lança InvalidRequestException se algum número for inválido (a API cobraria esses números sem os enviar).
     *
     * @param string[] $phones
     * @return array<string,mixed> {batch_id, total, sent, failed, queued, invalid, cost, results, ...}
     */
    public function bulk(array $phones, string $message, ?string $senderId = null): array
    {
        $v = $this->client->validator;
        $phones = $v->phones($phones, Validator::MAX_BULK_PHONES);
        $v->message($message);
        $v->sender($senderId);
        $payload = ['phones' => $phones, 'message' => $message];
        if ($senderId !== null) {
            $payload['sender_id'] = $senderId;
        }
        return $this->client->request('POST', '/sms/bulk', [], $payload)['data'] ?? [];
    }

    /**
     * Texto diferente por destinatário (até 2000 por pedido).
     *
     * @param array<int,array{phone:string,message:string}> $messages
     * @return array<string,mixed>
     */
    public function bulkMessages(array $messages, ?string $senderId = null): array
    {
        $v = $this->client->validator;
        if ($messages === []) {
            throw new InvalidRequestException("'messages' tem de ser uma lista não vazia");
        }
        if (count($messages) > Validator::MAX_BULK_MESSAGES) {
            throw new InvalidRequestException("'messages' admite no máximo " . Validator::MAX_BULK_MESSAGES . ' itens; para mais use bulk()');
        }
        $phones = [];
        foreach (array_values($messages) as $i => $m) {
            if (!is_array($m) || !isset($m['phone'], $m['message']) || !is_string($m['message'])) {
                throw new InvalidRequestException("messages[$i] precisa de 'phone' e 'message' (texto)");
            }
            $v->message($m['message'], "messages[$i].message");
            $phones[] = $m['phone'];
        }
        $normalized = $v->phones($phones, Validator::MAX_BULK_MESSAGES, 'messages[].phone');
        $v->sender($senderId);
        $clean = [];
        foreach (array_values($messages) as $i => $m) {
            $clean[] = ['phone' => $normalized[$i]] + $m;
        }
        $payload = ['messages' => $clean];
        if ($senderId !== null) {
            $payload['sender_id'] = $senderId;
        }
        return $this->client->request('POST', '/sms/bulk', [], $payload)['data'] ?? [];
    }

    /**
     * Estado de uma mensagem. $id é o `id` devolvido por send().
     *
     * @return array<string,mixed>
     */
    public function status(string $id): array
    {
        $this->client->validator->id($id);
        $r = $this->client->request('GET', '/sms/status/' . rawurlencode($id));
        return $r['data'] ?? $r;
    }

    /**
     * Histórico (corpo completo, com paginação). Filtros: sender_id, status, search, date_from, date_to, page, limit.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function history(array $filters = []): array
    {
        return $this->client->request('GET', '/sms/history', $filters);
    }

    /** Histórico em CSV (até 50000 linhas). Mesmos filtros de history(). @param array<string,mixed> $filters */
    public function exportHistory(array $filters = []): string
    {
        return $this->client->download('/sms/history/export', $filters);
    }

    /**
     * Agenda um envio.
     *
     * @param string[] $numbers
     * @param string $scheduledAt p.ex. "2026-12-01 09:00:00" (fuso da conta)
     * @param string $repeat none|daily|weekly|monthly
     * @return array<string,mixed>
     */
    public function schedule(array $numbers, string $message, string $scheduledAt, ?string $senderId = null, string $repeat = 'none'): array
    {
        $v = $this->client->validator;
        $numbers = $v->phones($numbers, Validator::MAX_SCHEDULE_PHONES, 'numbers');
        $v->message($message);
        $v->sender($senderId);
        if (trim($scheduledAt) === '') {
            throw new InvalidRequestException("'scheduledAt' é obrigatório");
        }
        if (!in_array($repeat, ['none', 'daily', 'weekly', 'monthly'], true)) {
            throw new InvalidRequestException("'repeat' tem de ser none, daily, weekly ou monthly");
        }
        $payload = ['numbers' => array_values($numbers), 'message' => $message, 'scheduled_at' => $scheduledAt, 'repeat' => $repeat];
        if ($senderId !== null) {
            $payload['sender_id'] = $senderId;
        }
        $r = $this->client->request('POST', '/sms/schedule', [], $payload);
        return $r['data'] ?? $r;
    }

    /** @return array<string,mixed> */
    public function schedules(): array
    {
        return $this->client->request('GET', '/sms/schedule');
    }
}
