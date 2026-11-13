<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

final class Dashboard extends AbstractResource
{
    /** @return array<string,mixed> */
    public function stats(): array
    {
        return $this->item('GET', '/dashboard/stats');
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function recentMessages(array $filters = []): array
    {
        return $this->body('GET', '/dashboard/recent-messages', $filters);
    }
}
