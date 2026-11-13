<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

final class ContactGroups extends AbstractResource
{
    /** @return array<string,mixed> */
    public function list(): array
    {
        return $this->body('GET', '/contacts/groups');
    }

    /**
     * @param array{descricao?:string,cor?:string,icone?:string,compartilhado?:bool} $options
     * @return array<string,mixed> {id, uuid, nome}
     */
    public function create(string $name, array $options = []): array
    {
        $this->required($name, 'nome');
        if (mb_strlen($name) > 255) {
            throw new InvalidRequestException("'nome' excede 255 caracteres");
        }
        return $this->item('POST', '/contacts/groups', [], ['nome' => $name] + $options);
    }

    /** @return array<string,mixed> */
    public function get(int $groupId): array
    {
        $this->id($groupId, 'groupId');
        return $this->item('GET', "/contacts/groups/$groupId");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function update(int $groupId, array $fields): array
    {
        $this->id($groupId, 'groupId');
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->body('PUT', "/contacts/groups/$groupId", [], $fields);
    }

    /** @param array<string,mixed> $filters page, limit... @return array<string,mixed> */
    public function contacts(int $groupId, array $filters = []): array
    {
        $this->id($groupId, 'groupId');
        return $this->body('GET', "/contacts/groups/$groupId/contacts", $filters);
    }

    /** @param int[] $contactIds @return array<string,mixed> */
    public function addContacts(int $groupId, array $contactIds): array
    {
        $this->id($groupId, 'groupId');
        return $this->body('POST', "/contacts/groups/$groupId/contacts", [], ['contact_ids' => $this->intList($contactIds, 'contact_ids')]);
    }
}
