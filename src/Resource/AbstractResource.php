<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Client;
use MozeSMS\Exception\InvalidRequestException;

/**
 * Convenção do SDK: operações sobre um recurso (get/create/update...) devolvem o conteúdo de `data`;
 * listagens devolvem o corpo completo (`data` + `pagination`/`total`...), para não perder a paginação.
 *
 * @internal
 */
abstract class AbstractResource
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $json
     * @return array<string,mixed> o conteúdo de `data` (ou o corpo todo se não houver)
     */
    protected function item(string $method, string $path, array $query = [], ?array $json = null): array
    {
        $r = $this->client->request($method, $path, $query, $json);
        return isset($r['data']) && is_array($r['data']) ? $r['data'] : $r;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $json
     * @return array<string,mixed> corpo completo
     */
    protected function body(string $method, string $path, array $query = [], ?array $json = null): array
    {
        return $this->client->request($method, $path, $query, $json);
    }

    /** @param mixed $value @param string[] $allowed */
    protected function oneOf($value, array $allowed, string $field): void
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidRequestException("'$field' inválido. Valores aceites: " . implode(', ', $allowed));
        }
    }

    protected function required(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidRequestException("'$field' é obrigatório");
        }
    }

    protected function id(int $id, string $field = 'id'): void
    {
        $this->client->validator->positiveInt($id, $field);
    }

    /**
     * @param mixed $ids
     * @return int[]
     */
    protected function intList($ids, string $field): array
    {
        if (!is_array($ids) || $ids === []) {
            throw new InvalidRequestException("'$field' tem de ser uma lista não vazia de inteiros");
        }
        $out = [];
        foreach ($ids as $v) {
            if (!is_int($v) && !(is_string($v) && ctype_digit($v))) {
                throw new InvalidRequestException("'$field' só aceita inteiros positivos");
            }
            $n = (int) $v;
            if ($n < 1) {
                throw new InvalidRequestException("'$field' só aceita inteiros positivos");
            }
            $out[] = $n;
        }
        return $out;
    }
}
