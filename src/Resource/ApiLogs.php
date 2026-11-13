<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

/** Registo dos seus próprios pedidos à API (útil para depurar integrações). */
final class ApiLogs extends AbstractResource
{
    /**
     * Filtros: status, method, endpoint, limit, offset, preview_chars, include_full (1).
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->body('GET', '/api-logs', $filters);
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/api-logs/$id");
    }
}
