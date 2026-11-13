<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

/** Sender IDs: pedido, listagem, partilha, filtros e documentos de aprovação. */
final class SenderIds extends AbstractResource
{
    public const TYPES = ['alfanumerico', 'numerico'];
    public const USES = ['marketing', 'transacional', 'otp', 'notificacao', 'outros', 'geral'];
    public const ENTITY_TYPES = ['empresa', 'igreja', 'ong', 'educacao', 'singular', 'outra'];
    public const FILTER_TYPES = ['keyword_block', 'keyword_allow', 'domain_block', 'rate_limit', 'message_format'];
    public const DOCUMENT_TYPES = [
        'certidao_comercial', 'alvara', 'nuit', 'nuit_sing', 'logotipo', 'contrato_social', 'licenca_funcionamento',
        'estatutos', 'acto_constitutivo', 'certificado_registo', 'alvara_educacao', 'estatutos_educacao', 'bi_frente',
        'bi_verso', 'bi_pass', 'passaporte_frente', 'passaporte_verso', 'id_representante', 'carta_assinada',
        'carta_autorizacao', 'carta_autorizacao_gerada', 'procuracao', 'autorizacao_atividade', 'outro',
    ];

    /**
     * Pede um sender ID. ATENÇÃO: o preço é DEBITADO do saldo no momento do pedido
     * (alfanumérico e short number têm preços diferentes). Sem saldo → InsufficientBalanceException
     * com required_amount/available_balance/missing_amount em getBody()['data'].
     *
     * Alfanumérico: 3-11 caracteres A-Z/0-9. Numérico: 3-11 dígitos. O nome é convertido para maiúsculas.
     *
     * @param array{entity_type?:string,entity_name?:string,compartilhado?:bool} $options
     * @return array<string,mixed>
     */
    public function request(string $senderId, string $type, string $use, string $description, array $options = []): array
    {
        $name = strtoupper(trim($senderId));
        $this->oneOf($type, self::TYPES, 'tipo');
        $this->oneOf($use, self::USES, 'uso');
        if ($type === 'alfanumerico' && !preg_match('/^[A-Z0-9]{3,11}$/', $name)) {
            throw new InvalidRequestException('Sender ID alfanumérico: 3 a 11 caracteres (A-Z, 0-9, sem espaços nem acentos)');
        }
        if ($type === 'numerico' && !preg_match('/^[0-9]{3,11}$/', $name)) {
            throw new InvalidRequestException('Sender ID numérico: 3 a 11 dígitos');
        }
        $this->required($description, 'descricao');
        if (mb_strlen($description) > 500) {
            throw new InvalidRequestException("'descricao' excede 500 caracteres");
        }
        $payload = ['sender_id' => $name, 'tipo' => $type, 'uso' => $use, 'descricao' => $description];
        if (isset($options['entity_type'])) {
            $this->oneOf($options['entity_type'], self::ENTITY_TYPES, 'entity_type');
            $payload['entity_type'] = $options['entity_type'];
        }
        if (isset($options['entity_name'])) {
            if (mb_strlen((string) $options['entity_name']) > 255) {
                throw new InvalidRequestException("'entity_name' excede 255 caracteres");
            }
            $payload['entity_name'] = (string) $options['entity_name'];
        }
        if (isset($options['compartilhado'])) {
            $payload['compartilhado'] = (bool) $options['compartilhado'];
        }
        return $this->item('POST', '/sender-ids/request', [], $payload);
    }

    /** Dos aprovados/ativos. Filtros: status, tipo, uso, search, has_filters, include_filters. @param array<string,mixed> $filters @return array<string,mixed> */
    public function list(array $filters = []): array
    {
        return $this->body('GET', '/sender-ids', $filters);
    }

    /** Todos, agrupados por estado (aprovado/pendente/rejeitado). @return array<string,mixed> */
    public function all(): array
    {
        return $this->item('GET', '/sender-ids/all');
    }

    /** @return array<string,mixed> */
    public function withFilters(): array
    {
        return $this->body('GET', '/sender-ids/with-filters');
    }

    /** @return array<string,mixed> */
    public function filtersOverview(): array
    {
        return $this->item('GET', '/sender-ids/filters/overview');
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/sender-ids/$id");
    }

    /** @return array<string,mixed> */
    public function share(int $id, bool $shared): array
    {
        $this->id($id);
        return $this->body('PUT', "/sender-ids/$id/share", [], ['compartilhado' => $shared ? 1 : 0]);
    }

    /** @return array<string,mixed> */
    public function setDefault(int $id): array
    {
        $this->id($id);
        return $this->body('PUT', "/sender-ids/$id/set-default");
    }

    /** @return array<string,mixed> */
    public function history(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/sender-ids/$id/history");
    }

    /** @return array<string,mixed> */
    public function activity(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/sender-ids/$id/activity");
    }

    // ---- filtros (regras anti-spam por sender ID) ----

    /** @return array<string,mixed> */
    public function filters(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/sender-ids/$id/filters");
    }

    /** @return array<string,mixed> */
    public function filterStats(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/sender-ids/$id/filters/stats");
    }

    /**
     * @param array<string,mixed>|null $config
     * @return array<string,mixed>
     */
    public function addFilter(int $id, string $type, string $value, ?array $config = null): array
    {
        $this->id($id);
        $this->oneOf($type, self::FILTER_TYPES, 'filter_type');
        $this->required($value, 'filter_value');
        if (mb_strlen($value) > 500) {
            throw new InvalidRequestException("'filter_value' excede 500 caracteres");
        }
        $payload = ['filter_type' => $type, 'filter_value' => $value];
        if ($config !== null) {
            $payload['filter_config'] = $config;
        }
        return $this->body('POST', "/sender-ids/$id/filters", [], $payload);
    }

    /** @param array<string,mixed> $fields filter_value, filter_config, is_active @return array<string,mixed> */
    public function updateFilter(int $id, int $filterId, array $fields): array
    {
        $this->id($id);
        $this->id($filterId, 'filterId');
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->body('PUT', "/sender-ids/$id/filters/$filterId", [], $fields);
    }

    // ---- documentos ----

    /** @return array<string,mixed> */
    public function documents(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/sender-ids/$id/documents");
    }

    /** Documentos que a equipa pediu. @return array<string,mixed> */
    public function documentRequests(int $id): array
    {
        $this->id($id);
        return $this->body('GET', "/sender-ids/$id/doc-requests");
    }

    /**
     * Envia um documento (PDF/imagem) do disco. $type: ver DOCUMENT_TYPES.
     *
     * @return array<string,mixed>
     */
    public function uploadDocument(int $id, string $type, string $filePath): array
    {
        $this->id($id);
        $this->oneOf($type, self::DOCUMENT_TYPES, 'tipo_documento');
        $r = $this->client->upload('POST', "/sender-ids/$id/documents", ['tipo_documento' => $type], ['document' => $filePath], 10 * 1024 * 1024);
        return isset($r['data']) && is_array($r['data']) ? $r['data'] : $r;
    }

    /** Conteúdo binário do documento. */
    public function downloadDocument(int $id, int $docId): string
    {
        $this->id($id);
        $this->id($docId, 'docId');
        return $this->client->download("/sender-ids/$id/documents/$docId/download");
    }
}
