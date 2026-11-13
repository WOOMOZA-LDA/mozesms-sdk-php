<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

/** Etiquetas de contactos WhatsApp. */
final class WhatsAppLabels extends AbstractResource
{
    /** @return array<string,mixed> */
    public function list(): array
    {
        return $this->body('GET', '/whatsapp/labels');
    }

    /** @return array<string,mixed> */
    public function create(string $name, ?string $color = null, ?string $description = null): array
    {
        $this->required($name, 'name');
        $payload = ['name' => $name];
        if ($color !== null) {
            $payload['color'] = $color;
        }
        if ($description !== null) {
            $payload['description'] = $description;
        }
        return $this->item('POST', '/whatsapp/labels', [], $payload);
    }

    /** @param array{name?:string,color?:string,description?:string} $fields @return array<string,mixed> */
    public function update(int $id, array $fields): array
    {
        $this->id($id);
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        return $this->body('PUT', "/whatsapp/labels/$id", [], $fields);
    }

    /** Contactos com esta etiqueta. @return array<string,mixed> */
    public function contacts(int $labelId): array
    {
        $this->id($labelId, 'labelId');
        return $this->body('GET', "/whatsapp/labels/$labelId/contacts");
    }

    /** Etiquetas de um contacto. @return array<string,mixed> */
    public function ofContact(int $contactId): array
    {
        $this->id($contactId, 'contactId');
        return $this->body('GET', "/whatsapp/contacts/$contactId/labels");
    }

    /** @param int[] $labelIds @return array<string,mixed> */
    public function addToContact(int $contactId, array $labelIds): array
    {
        $this->id($contactId, 'contactId');
        return $this->body('POST', "/whatsapp/contacts/$contactId/labels", [], ['label_ids' => $this->intList($labelIds, 'label_ids')]);
    }
}
