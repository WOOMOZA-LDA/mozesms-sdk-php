<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

/** Notificações da conta (saldo baixo, aprovações de sender ID...). */
final class Notifications extends AbstractResource
{
    /** @param array{limit?:int,include_message_events?:int,message_limit?:int} $filters @return array<string,mixed> */
    public function list(array $filters = []): array
    {
        return $this->body('GET', '/notifications', $filters);
    }

    /** @return array<string,mixed> */
    public function markRead(int $id): array
    {
        $this->id($id);
        return $this->body('POST', "/notifications/$id/read");
    }

    /** @return array<string,mixed> */
    public function readAll(): array
    {
        return $this->body('POST', '/notifications/read-all');
    }
}
