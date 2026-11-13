<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

/**
 * Automações WhatsApp. Tipos: chatbot (fluxos com passos), keyword (palavras-chave), welcome (boas-vindas)
 * e schedule (mensagens agendadas). Mais as definições de IA e o registo.
 */
final class WhatsAppAutomations extends AbstractResource
{
    public const TYPES = ['chatbot', 'keyword', 'welcome', 'schedule'];

    /** @param array<string,mixed> $filters session_id... @return array<string,mixed> */
    public function list(string $type, array $filters = []): array
    {
        $this->oneOf($type, self::TYPES, 'type');
        return $this->body('GET', "/whatsapp/automations/$type", $filters);
    }

    /**
     * Cria uma automação. $fields conforme o tipo (name, session_id, keywords, match_mode, message, response_message,
     * trigger, schedule_type, schedule_time, run_at, week_days, ...). `name` e `session_id` são obrigatórios.
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function create(string $type, array $fields): array
    {
        $this->oneOf($type, self::TYPES, 'type');
        $this->requireKeys($fields, ['name', 'session_id']);
        return $this->item('POST', "/whatsapp/automations/$type", [], $fields);
    }

    /** @return array<string,mixed> */
    public function get(string $type, int $id): array
    {
        $this->oneOf($type, self::TYPES, 'type');
        $this->id($id);
        return $this->item('GET', "/whatsapp/automations/$type/$id");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function update(string $type, int $id, array $fields): array
    {
        $this->oneOf($type, self::TYPES, 'type');
        $this->id($id);
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->item('PUT', "/whatsapp/automations/$type/$id", [], $fields);
    }

    /** Liga/desliga. @return array<string,mixed> */
    public function toggle(string $type, int $id): array
    {
        $this->oneOf($type, self::TYPES, 'type');
        $this->id($id);
        return $this->body('POST', "/whatsapp/automations/$type/$id/toggle");
    }

    /** Executa já uma automação agendada (COBRA as mensagens). @return array<string,mixed> */
    public function runScheduleNow(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/whatsapp/automations/schedule/$id/run-now");
    }

    /**
     * Passo de um fluxo de chatbot.
     *
     * @param array<string,mixed> $fields message, type, options, step_order, is_final...
     * @return array<string,mixed>
     */
    public function addStep(int $flowId, array $fields): array
    {
        $this->id($flowId, 'flowId');
        if ($fields === []) {
            throw new InvalidRequestException('Passo vazio');
        }
        return $this->item('POST', "/whatsapp/automations/chatbot/$flowId/steps", [], $fields);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function updateStep(int $flowId, int $stepId, array $fields): array
    {
        $this->id($flowId, 'flowId');
        $this->id($stepId, 'stepId');
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->item('PUT', "/whatsapp/automations/chatbot/$flowId/steps/$stepId", [], $fields);
    }

    // ---- IA ----

    /** @return array<string,mixed> */
    public function aiSettings(): array
    {
        return $this->item('GET', '/whatsapp/automations/ai/settings');
    }

    /**
     * @param array<string,mixed> $settings is_enabled, provider, model, temperature, max_tokens, ai_custom_rules, ...
     * @return array<string,mixed>
     */
    public function saveAiSettings(array $settings): array
    {
        if ($settings === []) {
            throw new InvalidRequestException('Definições vazias');
        }
        return $this->body('PUT', '/whatsapp/automations/ai/settings', [], $settings);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function aiConversations(array $filters = []): array
    {
        return $this->body('GET', '/whatsapp/automations/ai/conversations', $filters);
    }

    /** Testa a resposta da IA sem enviar nada. @return array<string,mixed> */
    public function testAi(string $message, ?string $sessionId = null): array
    {
        $this->required($message, 'message');
        $payload = ['message' => $message];
        if ($sessionId !== null) {
            $payload['session_id'] = $sessionId;
        }
        return $this->body('POST', '/whatsapp/automations/ai/test', [], $payload);
    }

    /** Registo de execuções das automações. @param array<string,mixed> $filters @return array<string,mixed> */
    public function log(array $filters = []): array
    {
        return $this->body('GET', '/whatsapp/automation/log', $filters);
    }

    /**
     * @param array<string,mixed> $fields
     * @param string[] $keys
     */
    private function requireKeys(array $fields, array $keys): void
    {
        foreach ($keys as $k) {
            if (!isset($fields[$k]) || (is_string($fields[$k]) && trim($fields[$k]) === '')) {
                throw new InvalidRequestException("'$k' é obrigatório");
            }
        }
    }
}
