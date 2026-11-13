<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

/** Campanhas WhatsApp (envio em massa com fila). */
final class WhatsAppCampaigns extends AbstractResource
{
    /** @param array{session_id?:string,status?:string} $filters @return array<string,mixed> */
    public function list(array $filters = []): array
    {
        return $this->body('GET', '/whatsapp/campaigns', $filters);
    }

    /**
     * Cria a campanha. $fields: message, recipients/phones, media... conforme o painel; session_id e name são obrigatórios.
     * Se passar `phones`, são validados e normalizados.
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function create(string $sessionId, string $name, array $fields): array
    {
        $this->required($sessionId, 'session_id');
        $this->required($name, 'name');
        if (isset($fields['phones'])) {
            $fields['phones'] = $this->client->validator->phones($fields['phones'], 100000);
        }
        return $this->item('POST', '/whatsapp/campaigns', [], ['session_id' => $sessionId, 'name' => $name] + $fields);
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/whatsapp/campaigns/$id");
    }

    /** Começa a enviar (COBRA cada mensagem). @return array<string,mixed> */
    public function start(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/whatsapp/campaigns/$id/start");
    }

    /** @return array<string,mixed> */
    public function pause(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/whatsapp/campaigns/$id/pause");
    }

    /** @return array<string,mixed> */
    public function resume(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/whatsapp/campaigns/$id/resume");
    }

    /** Para o envio; as mensagens já enviadas e o registo ficam. @return array<string,mixed> */
    public function cancel(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/whatsapp/campaigns/$id/cancel");
    }

    /** @return array<string,mixed> */
    public function report(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/whatsapp/campaigns/$id/report");
    }
}
