<?php

declare(strict_types=1);

namespace MozeSMS\Resource;

use MozeSMS\Exception\InvalidRequestException;

final class Contacts extends AbstractResource
{
    /**
     * Filtros: search, status, group_id, page, limit.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function list(array $filters = []): array
    {
        return $this->body('GET', '/contacts', $filters);
    }

    /**
     * Cria um contacto. $name e $phone são obrigatórios (7-20 dígitos). 409 se o número já existir.
     * Campos extra: sobrenome, telefone_adicional, email, empresa, cargo, departamento, cidade, provincia, pais,
     * endereco, tags, campos_personalizados, notas, opt_in, fonte.
     *
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function create(string $name, string $phone, array $extra = []): array
    {
        $this->required($name, 'nome');
        $this->checkPhone($phone);
        return $this->item('POST', '/contacts', [], ['nome' => $name, 'telefone' => $phone] + $extra);
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        $this->id($id);
        return $this->item('GET', "/contacts/$id");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function update(int $id, array $fields): array
    {
        $this->id($id);
        if ($fields === []) {
            throw new InvalidRequestException('Nada para atualizar');
        }
        if (isset($fields['telefone'])) {
            $this->checkPhone((string) $fields['telefone']);
        }
        return $this->item('PUT', "/contacts/$id", [], $fields);
    }

    /**
     * Importa um CSV (ou vCard) do disco, até 50000 linhas. Opcionalmente já para um grupo.
     *
     * @return array<string,mixed> {inserted, duplicates, invalid, ...}
     */
    public function import(string $csvPath, ?int $groupId = null): array
    {
        $fields = [];
        if ($groupId !== null) {
            $this->id($groupId, 'groupId');
            $fields['group_id'] = $groupId;
        }
        $r = $this->client->upload('POST', '/contacts/import', $fields, ['csv_file' => $csvPath], 10 * 1024 * 1024);
        return isset($r['data']) && is_array($r['data']) ? $r['data'] : $r;
    }

    /**
     * Mete contactos em grupos (vários para vários).
     *
     * @param int[] $contactIds
     * @param int[] $groupIds
     * @return array<string,mixed>
     */
    public function bulkAssign(array $contactIds, array $groupIds): array
    {
        return $this->body('POST', '/contacts/bulk-assign', [], [
            'contact_ids' => $this->intList($contactIds, 'contact_ids'),
            'group_ids' => $this->intList($groupIds, 'group_ids'),
        ]);
    }

    private function checkPhone(string $phone): void
    {
        $d = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($d) < 7 || strlen($d) > 20) {
            throw new InvalidRequestException("Telefone inválido: '$phone' (7 a 20 dígitos)");
        }
    }
}
