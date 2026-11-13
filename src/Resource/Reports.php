<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

/** Relatórios (só leitura). Filtros habituais: date_from, date_to, sender_id, status, page, limit. */
final class Reports extends AbstractResource
{
    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function sms(array $filters = []): array
    {
        return $this->body('GET', '/reports/sms', $filters);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function delivery(array $filters = []): array
    {
        return $this->body('GET', '/reports/delivery', $filters);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function summary(array $filters = []): array
    {
        return $this->body('GET', '/reports/summary', $filters);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function byStatus(array $filters = []): array
    {
        return $this->body('GET', '/reports/by-status', $filters);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function byGateway(array $filters = []): array
    {
        return $this->body('GET', '/reports/by-gateway', $filters);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function costs(array $filters = []): array
    {
        return $this->body('GET', '/reports/costs', $filters);
    }
}
