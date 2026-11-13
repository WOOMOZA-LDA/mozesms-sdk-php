<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

/** Modelos de mensagem. Ficam "pendentes" até serem aprovados. */
final class Templates extends AbstractResource
{
    public const CATEGORIES = ['marketing', 'transacional', 'otp', 'notificacao', 'cobranca', 'confirmacao', 'outros'];

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function list(array $filters = []): array
    {
        return $this->body('GET', '/templates', $filters);
    }

    /** @return array<string,mixed> {id, nome, categoria, status, char_count, segment_count} */
    public function create(string $name, string $message, string $category = 'outros'): array
    {
        $this->required($name, 'nome');
        $this->required($message, 'mensagem');
        $this->oneOf($category, self::CATEGORIES, 'categoria');
        if (mb_strlen($message) > 918) {
            throw new InvalidRequestException("'mensagem' excede 918 caracteres");
        }
        if (mb_strlen($name) > 255) {
            throw new InvalidRequestException("'nome' excede 255 caracteres");
        }
        return $this->item('POST', '/templates', [], ['nome' => $name, 'mensagem' => $message, 'categoria' => $category]);
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/templates/$id");
    }

    /** @param array{nome?:string,mensagem?:string,categoria?:string} $fields @return array<string,mixed> */
    public function update(int $id, array $fields): array
    {
        $this->id($id);
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        if (isset($fields['categoria'])) {
            $this->oneOf($fields['categoria'], self::CATEGORIES, 'categoria');
        }
        if (isset($fields['mensagem']) && mb_strlen((string) $fields['mensagem']) > 918) {
            throw new InvalidRequestException("'mensagem' excede 918 caracteres");
        }
        return $this->body('PUT', "/templates/$id", [], $fields);
    }
}
