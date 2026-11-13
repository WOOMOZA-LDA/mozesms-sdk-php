<?php

declare(strict_types=1);

// Pedir um sender ID. O PREÇO É DEBITADO DO SALDO no momento do pedido.
// O SDK não apaga sender IDs: para remover um, use o painel.
$moze = require __DIR__ . '/_client.php';

use MozeSMS\Exception\ConflictException;
use MozeSMS\Exception\InsufficientBalanceException;
use MozeSMS\Exception\InvalidRequestException;
use MozeSMS\Exception\ValidationException;

$nome = $argv[1] ?? 'MINHALOJA';

try {
    $r = $moze->senderIds->request($nome, 'alfanumerico', 'transacional', 'Notificações de encomendas da loja online', [
        'entity_type' => 'empresa',
        'entity_name' => 'Minha Loja, Lda',
    ]);
    echo "Pedido criado, fica pendente até ser aprovado:\n";
    print_r($r);

    // Documentos de aprovação (PDF/imagem). Tipos: SenderIds::DOCUMENT_TYPES
    // $moze->senderIds->uploadDocument((int) $r['id'], 'alvara', __DIR__ . '/alvara.pdf');

    // Ver o estado de todos, agrupados
    $todos = $moze->senderIds->all();
    foreach (['aprovado', 'pendente', 'rejeitado'] as $estado) {
        $nomes = array_column($todos['grouped'][$estado] ?? [], 'sender_id');
        printf("%-10s %s\n", $estado, $nomes ? implode(', ', $nomes) : '-');
    }
} catch (InvalidRequestException $e) {
    echo 'Dados inválidos: ' . $e->getMessage() . "\n";   // p.ex. nome com espaços ou com menos de 3 caracteres
} catch (InsufficientBalanceException $e) {
    $d = $e->getBody()['data'] ?? [];
    printf("Saldo insuficiente: faltam %s MZN (preço %s)\n", $d['missing_amount'] ?? '?', $d['required_amount'] ?? '?');
} catch (ConflictException|ValidationException $e) {
    echo 'Recusado: ' . $e->getMessage() . "\n";           // p.ex. já existe para a empresa
}
