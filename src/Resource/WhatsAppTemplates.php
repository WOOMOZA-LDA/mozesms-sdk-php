<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

/** Modelos de mensagem WhatsApp. */
final class WhatsAppTemplates extends AbstractResource
{
    /** @param array{session_id?:string,categoria?:string,search?:string} $filters @return array<string,mixed> */
    public function list(array $filters = []): array
    {
        return $this->body('GET', '/whatsapp/templates', $filters);
    }

    /**
     * Campos extra: categoria, session_id, variables, buttons, footer, media_type, media_url, is_active.
     *
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function create(string $name, string $message, array $extra = []): array
    {
        $this->required($name, 'nome');
        $this->required($message, 'mensagem');
        return $this->item('POST', '/whatsapp/templates', [], ['nome' => $name, 'mensagem' => $message] + $extra);
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/whatsapp/templates/$id");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function update(int $id, array $fields): array
    {
        $this->id($id);
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->item('PUT', "/whatsapp/templates/$id", [], $fields);
    }

    /** Ativa/desativa. @return array<string,mixed> */
    public function toggle(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/whatsapp/templates/$id/toggle");
    }

    /**
     * Envia o modelo (COBRA a mensagem). $variables substitui as variáveis do texto.
     *
     * @param array<string,string> $variables
     * @return array<string,mixed>
     */
    public function send(int $id, string $sessionId, string $to, array $variables = []): array
    {
        $this->id($id);
        $this->required($sessionId, 'session_id');
        $to = $this->client->validator->phone($to, 'to');
        $payload = ['session_id' => $sessionId, 'to' => $to];
        if ($variables) {
            $payload['variables'] = $variables;
        }
        return $this->body('POST', "/whatsapp/templates/$id/send", [], $payload);
    }
}
