<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Client;
use MozeSMS\Exception\InvalidRequestException;

/**
 * WhatsApp: sessões, envio (texto, média, ficheiros, localização, grupos), grupos, contactos e webhooks da sessão.
 *
 * Sub-recursos: $whatsapp->templates, ->labels, ->campaigns, ->automations, ->billing.
 * Números em qualquer país (formato internacional); o SDK normaliza para dígitos E.164.
 * Não há métodos de apagar (sessões, webhooks, ficheiros): faça-o no painel.
 */
final class WhatsApp extends AbstractResource
{
    public const MEDIA_TYPES = ['image', 'video', 'audio', 'document', 'pdf'];

    public WhatsAppTemplates $templates;
    public WhatsAppLabels $labels;
    public WhatsAppCampaigns $campaigns;
    public WhatsAppAutomations $automations;
    public WhatsAppBilling $billing;

    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->templates = new WhatsAppTemplates($client);
        $this->labels = new WhatsAppLabels($client);
        $this->campaigns = new WhatsAppCampaigns($client);
        $this->automations = new WhatsAppAutomations($client);
        $this->billing = new WhatsAppBilling($client);
    }

    // ---- sessões ----

    /** Planos disponíveis (precisa de um plan_id para criar sessão). Atalho de billing->plans(). @return array<string,mixed> */
    public function plans(): array
    {
        return $this->billing->plans();
    }

    /** @return array<string,mixed> */
    public function sessions(): array
    {
        return $this->body('GET', '/whatsapp/sessions');
    }

    /** Cria uma sessão e subscreve o plano (pode debitar o saldo). @return array<string,mixed> */
    public function createSession(string $name, int $planId, int $billingDay = 1): array
    {
        $this->required($name, 'name');
        $this->id($planId, 'planId');
        if ($billingDay < 1 || $billingDay > 28) {
            throw new InvalidRequestException("'billingDay' tem de estar entre 1 e 28");
        }
        return $this->item('POST', '/whatsapp/sessions', [], ['name' => $name, 'plan_id' => $planId, 'billing_day' => $billingDay]);
    }

    /** @return array<string,mixed> */
    public function session(string $sessionId): array
    {
        return $this->item('GET', $this->s($sessionId));
    }

    /** @return array<string,mixed> */
    public function sessionDetails(string $sessionId): array
    {
        return $this->item('GET', $this->s($sessionId) . '/details');
    }

    /** Inicia a ligação (depois leia o QR). @return array<string,mixed> */
    public function connect(string $sessionId): array
    {
        return $this->body('POST', $this->s($sessionId) . '/connect');
    }

    /** Ligação por código de emparelhamento em vez de QR. @return array<string,mixed> */
    public function connectWithCode(string $sessionId, string $phone): array
    {
        $phone = $this->client->validator->phone($phone);
        return $this->body('POST', $this->s($sessionId) . '/connect/code', [], ['phone' => $phone]);
    }

    /** @return array<string,mixed> */
    public function qr(string $sessionId): array
    {
        return $this->body('GET', $this->s($sessionId) . '/qr');
    }

    /** QR como imagem (binário PNG). */
    public function qrImage(string $sessionId): string
    {
        return $this->client->download($this->s($sessionId) . '/qr/image');
    }

    /** Verifica se um número tem WhatsApp. @return array<string,mixed> */
    public function lookup(string $sessionId, string $phone): array
    {
        $phone = $this->client->validator->phone($phone);
        return $this->body('GET', $this->s($sessionId) . '/lookup/' . rawurlencode($phone));
    }

    /** @return array<string,mixed> */
    public function settings(string $sessionId): array
    {
        return $this->item('GET', $this->s($sessionId) . '/settings');
    }

    /** @param array<string,mixed> $settings @return array<string,mixed> */
    public function updateSettings(string $sessionId, array $settings): array
    {
        if ($settings === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->body('PUT', $this->s($sessionId) . '/settings', [], $settings);
    }

    // ---- envio ----

    /**
     * Envia texto. Cada mensagem enviada é cobrada; não repita sem verificar messages().
     *
     * @return array<string,mixed>
     */
    public function sendText(string $sessionId, string $phone, string $message): array
    {
        $this->required($sessionId, 'session_id');
        $phone = $this->client->validator->phone($phone);
        $this->client->validator->message($message);
        return $this->body('POST', '/whatsapp/send', [], ['session_id' => $sessionId, 'phone' => $phone, 'message' => $message, 'type' => 'text']);
    }

    /**
     * Texto para vários números: os primeiros 5 saem já, os restantes vão para a fila.
     *
     * @param string[] $phones
     * @return array<string,mixed> {sent_count, queued_count, total, results}
     */
    public function sendTextBulk(string $sessionId, array $phones, string $message): array
    {
        $this->required($sessionId, 'session_id');
        $this->client->validator->message($message);
        $phones = $this->client->validator->phones($phones, 100000);
        return $this->body('POST', '/whatsapp/send', [], ['session_id' => $sessionId, 'phones' => $phones, 'message' => $message, 'type' => 'text']);
    }

    /**
     * Média por URL pública ou base64. $type: image, video, audio, document, pdf.
     *
     * @param array{caption?:string,filename?:string,ptt?:bool} $options
     * @return array<string,mixed>
     */
    public function sendMedia(string $sessionId, string $phone, string $type, string $mediaUrl, array $options = []): array
    {
        $this->required($sessionId, 'session_id');
        $phone = $this->client->validator->phone($phone);
        $this->oneOf($type, self::MEDIA_TYPES, 'type');
        $this->required($mediaUrl, 'media');
        $field = $type === 'pdf' ? 'document' : $type;
        return $this->body('POST', '/whatsapp/send/' . $type, [], [
            'session_id' => $sessionId, 'phone' => $phone, $field => $mediaUrl,
        ] + $options);
    }

    /**
     * Média/ficheiro do disco, enviado diretamente (até 16 MB).
     *
     * @return array<string,mixed>
     */
    public function sendFile(string $sessionId, string $phone, string $filePath, ?string $caption = null): array
    {
        $this->required($sessionId, 'session_id');
        $phone = $this->client->validator->phone($phone);
        $fields = ['session_id' => $sessionId, 'phone' => $phone];
        if ($caption !== null) {
            $fields['caption'] = $caption;
        }
        return $this->client->upload('POST', '/whatsapp/send/upload', $fields, ['file' => $filePath], 16 * 1024 * 1024);
    }

    /** Carrega um ficheiro para depois o referenciar por id/URL. @return array<string,mixed> */
    public function uploadMedia(string $filePath): array
    {
        $r = $this->client->upload('POST', '/whatsapp/upload', [], ['file' => $filePath], 16 * 1024 * 1024);
        return isset($r['data']) && is_array($r['data']) ? $r['data'] : $r;
    }

    /** Conteúdo de um ficheiro carregado (binário). */
    public function media(string $id): string
    {
        $this->required($id, 'id');
        return $this->client->download('/whatsapp/media/' . rawurlencode($id));
    }

    /** @return array<string,mixed> */
    public function sendLocation(string $sessionId, string $phone, float $latitude, float $longitude, ?string $name = null, ?string $address = null): array
    {
        $this->required($sessionId, 'session_id');
        $phone = $this->client->validator->phone($phone);
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidRequestException('Coordenadas fora do intervalo válido');
        }
        $payload = ['session_id' => $sessionId, 'phone' => $phone, 'latitude' => $latitude, 'longitude' => $longitude];
        if ($name !== null) {
            $payload['name'] = $name;
        }
        if ($address !== null) {
            $payload['address'] = $address;
        }
        return $this->body('POST', '/whatsapp/send/location', [], $payload);
    }

    /** @return array<string,mixed> */
    public function sendGroup(string $sessionId, string $groupJid, string $message): array
    {
        $this->required($sessionId, 'session_id');
        $this->required($groupJid, 'group_jid');
        $this->client->validator->message($message);
        return $this->body('POST', '/whatsapp/send/group', [], ['session_id' => $sessionId, 'group_jid' => $groupJid, 'message' => $message]);
    }

    /** Histórico de mensagens. @param array<string,mixed> $filters @return array<string,mixed> */
    public function messages(array $filters = []): array
    {
        return $this->body('GET', '/whatsapp/messages', $filters);
    }

    /** @return array<string,mixed> */
    public function messageStatus(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/whatsapp/messages/$id/status");
    }

    // ---- grupos ----

    /** @param array<string,mixed> $filters filter, sort @return array<string,mixed> */
    public function groups(string $sessionId, array $filters = []): array
    {
        return $this->body('GET', $this->s($sessionId) . '/groups', $filters);
    }

    /** @return array<string,mixed> */
    public function groupInfo(string $sessionId, string $groupJid): array
    {
        $this->required($groupJid, 'groupJid');
        return $this->body('GET', $this->s($sessionId) . '/groups/' . rawurlencode($groupJid) . '/info');
    }

    /** @return array<string,mixed> */
    public function groupMembers(string $sessionId, string $groupJid): array
    {
        $this->required($groupJid, 'groupJid');
        return $this->body('GET', $this->s($sessionId) . '/groups/' . rawurlencode($groupJid) . '/members');
    }

    /**
     * Cria um grupo (mínimo 2 membros).
     *
     * @param string[] $members números de qualquer país
     * @return array<string,mixed>
     */
    public function createGroup(string $name, array $members, ?string $topic = null): array
    {
        $this->required($name, 'name');
        if (count($members) < 2) {
            throw new InvalidRequestException("'members' precisa de pelo menos 2 números");
        }
        $payload = ['name' => $name, 'members' => $this->client->validator->phones($members, 1024, 'members')];
        if ($topic !== null) {
            $payload['topic'] = $topic;
        }
        return $this->item('POST', '/whatsapp/groups/create', [], $payload);
    }

    /** @param array{name?:string,topic?:string,announce_only?:bool} $fields @return array<string,mixed> */
    public function updateGroup(string $groupJid, array $fields): array
    {
        $this->required($groupJid, 'groupJid');
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->body('PUT', '/whatsapp/groups/' . rawurlencode($groupJid), [], $fields);
    }

    /** @param string[] $members @return array<string,mixed> */
    public function addGroupMembers(string $groupJid, array $members): array
    {
        return $this->groupMembersOp($groupJid, 'add', $members);
    }

    /** @param string[] $members @return array<string,mixed> */
    public function removeGroupMembers(string $groupJid, array $members): array
    {
        return $this->groupMembersOp($groupJid, 'remove', $members);
    }

    /** @param string[] $members @return array<string,mixed> */
    public function promoteGroupMembers(string $groupJid, array $members): array
    {
        return $this->groupMembersOp($groupJid, 'promote', $members);
    }

    /** @param string[] $members @return array<string,mixed> */
    public function demoteGroupMembers(string $groupJid, array $members): array
    {
        return $this->groupMembersOp($groupJid, 'demote', $members);
    }

    /** A sessão sai do grupo. @return array<string,mixed> */
    public function leaveGroup(string $groupJid): array
    {
        $this->required($groupJid, 'groupJid');
        return $this->body('POST', '/whatsapp/groups/' . rawurlencode($groupJid) . '/leave');
    }

    // ---- contactos ----

    /** @return array<string,mixed> */
    public function profile(string $phone): array
    {
        $phone = $this->client->validator->phone($phone);
        return $this->body('GET', '/whatsapp/profile/' . rawurlencode($phone));
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function contacts(string $sessionId, array $filters = []): array
    {
        return $this->body('GET', $this->s($sessionId) . '/contacts', $filters);
    }

    /** @return array<string,mixed> */
    public function syncContacts(string $sessionId): array
    {
        return $this->body('POST', $this->s($sessionId) . '/sync/contacts');
    }

    /** @return array<string,mixed> */
    public function syncGroups(string $sessionId): array
    {
        return $this->body('POST', $this->s($sessionId) . '/sync/groups');
    }

    /** @return array<string,mixed> */
    public function blockContact(string $sessionId, string $phone): array
    {
        $phone = $this->client->validator->phone($phone);
        return $this->body('POST', $this->s($sessionId) . '/block/' . rawurlencode($phone));
    }

    /** @return array<string,mixed> */
    public function unblockContact(string $sessionId, string $phone): array
    {
        $phone = $this->client->validator->phone($phone);
        return $this->body('POST', $this->s($sessionId) . '/unblock/' . rawurlencode($phone));
    }

    // ---- webhooks da sessão (eventos de mensagens recebidas/estado) ----

    /** @return array<string,mixed> */
    public function webhooks(string $sessionId): array
    {
        return $this->body('GET', $this->s($sessionId) . '/webhooks');
    }

    /**
     * @param string[]|string $events
     * @return array<string,mixed>
     */
    public function registerWebhook(string $sessionId, string $url, $events = '*', ?string $secret = null): array
    {
        if (!preg_match('#^https://[^\s/]+#i', $url)) {
            throw new InvalidRequestException("'url' tem de ser https");
        }
        $payload = ['url' => $url, 'events' => $events];
        if ($secret !== null) {
            $payload['secret'] = $secret;
        }
        return $this->body('POST', $this->s($sessionId) . '/webhooks', [], $payload);
    }

    /** @return array<string,mixed> */
    public function testWebhook(string $sessionId, string $webhookId): array
    {
        $this->required($webhookId, 'webhookId');
        return $this->body('POST', $this->s($sessionId) . '/webhooks/' . rawurlencode($webhookId) . '/test');
    }

    private function s(string $sessionId): string
    {
        $this->required($sessionId, 'sessionId');
        return '/whatsapp/sessions/' . rawurlencode($sessionId);
    }

    /** @param string[] $members @return array<string,mixed> */
    private function groupMembersOp(string $groupJid, string $op, array $members): array
    {
        $this->required($groupJid, 'groupJid');
        return $this->body('POST', '/whatsapp/groups/' . rawurlencode($groupJid) . "/members/$op", [], [
            'members' => $this->client->validator->phones($members, 1024, 'members'),
        ]);
    }
}
