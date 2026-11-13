<?php

declare(strict_types=1);

// Contactos → grupo → campanha. O envio da campanha (send) gasta saldo.
$moze = require __DIR__ . '/_client.php';

use MozeSMS\Exception\ApiException;
use MozeSMS\Exception\InvalidRequestException;

try {
    // 1) Grupo e contactos
    $grupo = $moze->groups->create('Clientes VIP', ['descricao' => 'Clientes com mais de 5 compras', 'cor' => '#3B82F6']);
    $ana = $moze->contacts->create('Ana Silva', '846000000', ['email' => 'ana@exemplo.co', 'cidade' => 'Maputo']);
    $rui = $moze->contacts->create('Rui Costa', '847000000');
    $moze->groups->addContacts((int) $grupo['id'], [(int) $ana['id'], (int) $rui['id']]);

    // Ou importar de um CSV (até 50000 linhas), já para o grupo:
    // $moze->contacts->import(__DIR__ . '/contactos.csv', (int) $grupo['id']);

    // 2) Campanha: rascunho → destinatários → enviar
    $camp = $moze->campaigns->create('Promoção de Natal', 'Olá {{nome}}, 20% de desconto até domingo!', 'MozeSMS');
    $id = (int) $camp['id'];
    $moze->campaigns->addRecipients($id, [], [], [(int) $grupo['id']]);   // phones, contactIds, groupIds
    printf("Campanha %d com %d destinatários\n", $id, count($moze->campaigns->recipients($id)['data'] ?? []));

    // 3) Só quando tiver a certeza (DEBITA o saldo; não é repetível):
    // $moze->campaigns->send($id);
    // print_r($moze->campaigns->report($id));

    // Templates reutilizáveis (ficam pendentes até serem aprovados)
    $moze->templates->create('Boas-vindas', 'Bem-vindo à nossa loja!', 'marketing');
} catch (InvalidRequestException $e) {
    echo 'Dados inválidos: ' . $e->getMessage() . "\n";
} catch (ApiException $e) {
    // 409 = contacto com este número já existe
    echo "Erro {$e->getHttpStatus()}: {$e->getMessage()}\n";
}
