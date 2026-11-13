<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

/** Campanhas SMS: criar → adicionar destinatários → enviar. */
final class Campaigns extends AbstractResource
{
    /** @param array<string,mixed> $filters status, search, page, limit @return array<string,mixed> */
    public function list(array $filters = []): array
    {
        return $this->body('GET', '/campaigns', $filters);
    }

    /** CSV. @param array<string,mixed> $filters */
    public function export(array $filters = []): string
    {
        return $this->client->download('/campaigns/export', $filters);
    }

    /**
     * Cria um rascunho. $sendAt opcional (futuro) agenda o envio.
     *
     * @param string|int|null $senderId id numérico ou nome do sender ID aprovado
     * @return array<string,mixed>
     */
    public function create(string $name, string $message, $senderId = null, ?string $sendAt = null): array
    {
        $this->required($name, 'nome');
        $this->client->validator->message($message, 'mensagem');
        if (mb_strlen($name) > 255) {
            throw new InvalidRequestException("'nome' excede 255 caracteres");
        }
        if (mb_strlen($message) > 918) {
            throw new InvalidRequestException("'mensagem' excede 918 caracteres");
        }
        $payload = ['nome' => $name, 'mensagem' => $message];
        if ($senderId !== null) {
            $payload['sender_id'] = $senderId;
        }
        if ($sendAt !== null) {
            $payload['data_envio'] = $sendAt;
        }
        return $this->item('POST', '/campaigns', [], $payload);
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/campaigns/$id");
    }

    /** @param array<string,mixed> $fields nome, mensagem, sender_id, data_envio @return array<string,mixed> */
    public function update(int $id, array $fields): array
    {
        $this->id($id);
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->item('PUT', "/campaigns/$id", [], $fields);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function recipients(int $id, array $filters = []): array
    {
        $this->id($id);
        return $this->body('GET', "/campaigns/$id/recipients", $filters);
    }

    /**
     * Adiciona destinatários por números, contactos e/ou grupos (pelo menos um). Só em rascunho/agendada.
     *
     * @param string[] $phones
     * @param int[] $contactIds
     * @param int[] $groupIds
     * @return array<string,mixed>
     */
    public function addRecipients(int $id, array $phones = [], array $contactIds = [], array $groupIds = []): array
    {
        $this->id($id);
        if (!$phones && !$contactIds && !$groupIds) {
            throw new InvalidRequestException('Indique phones, contactIds ou groupIds');
        }
        $payload = [];
        if ($phones) {
            $payload['phones'] = $this->client->validator->phones($phones, 100000);
        }
        if ($contactIds) {
            $payload['contact_ids'] = $this->intList($contactIds, 'contact_ids');
        }
        if ($groupIds) {
            $payload['group_ids'] = $this->intList($groupIds, 'group_ids');
        }
        return $this->body('POST', "/campaigns/$id/recipients", [], $payload);
    }

    /**
     * Dispara a campanha (DEBITA o saldo). Não é repetível: se der timeout, consulte get()/report() antes de tentar de novo.
     *
     * @return array<string,mixed>
     */
    public function send(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/campaigns/$id/send");
    }

    /** @return array<string,mixed> */
    public function cancel(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/campaigns/$id/cancel");
    }

    /** @return array<string,mixed> */
    public function report(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/campaigns/$id/report");
    }

    /** @return array<string,mixed> */
    public function duplicate(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/campaigns/$id/duplicate");
    }
}
