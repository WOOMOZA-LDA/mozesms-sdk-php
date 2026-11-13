<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

/** Mensagens automáticas de aniversário. */
final class Birthdays extends AbstractResource
{
    /** @return array<string,mixed> */
    public function config(): array
    {
        return $this->item('GET', '/birthdays/config');
    }

    /** @param array<string,mixed> $config @return array<string,mixed> */
    public function saveConfig(array $config): array
    {
        if ($config === []) {
            throw new InvalidRequestException('Configuração vazia');
        }
        return $this->body('PUT', '/birthdays/config', [], $config);
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function contacts(array $filters = []): array
    {
        return $this->body('GET', '/birthdays/contacts', $filters);
    }

    /** @param array<string,mixed> $contact nome, telefone, data de nascimento... @return array<string,mixed> */
    public function createContact(array $contact): array
    {
        if ($contact === []) {
            throw new InvalidRequestException('Contacto vazio');
        }
        return $this->body('POST', '/birthdays/contacts', [], $contact);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function updateContact(int $id, array $fields): array
    {
        $this->id($id);
        return $this->body('PUT', "/birthdays/contacts/$id", [], $fields);
    }

    /** @return array<string,mixed> */
    public function upcoming(): array
    {
        return $this->body('GET', '/birthdays/upcoming');
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function history(array $filters = []): array
    {
        return $this->body('GET', '/birthdays/history', $filters);
    }

    /** Envia já os aniversários de hoje (DEBITA o saldo). @return array<string,mixed> */
    public function process(): array
    {
        return $this->body('POST', '/birthdays/process');
    }
}
