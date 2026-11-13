<?php

declare(strict_types=1);

// Como tratar TODOS os erros. Ordem: do mais específico para o mais geral.
$moze = require __DIR__ . '/_client.php';

use MozeSMS\Exception\ApiException;
use MozeSMS\Exception\AuthenticationException;
use MozeSMS\Exception\ConflictException;
use MozeSMS\Exception\ConnectionException;
use MozeSMS\Exception\InsufficientBalanceException;
use MozeSMS\Exception\InvalidRequestException;
use MozeSMS\Exception\InvalidResponseException;
use MozeSMS\Exception\MozeSMSException;
use MozeSMS\Exception\NotFoundException;
use MozeSMS\Exception\RateLimitException;
use MozeSMS\Exception\ServerException;
use MozeSMS\Exception\TimeoutException;
use MozeSMS\Exception\ValidationException;

try {
    $moze->sms->send($argv[1] ?? '846000000', 'Teste', 'MozeSMS');
} catch (InvalidRequestException $e) {
    // Recusado pelo SDK ANTES do pedido: nada enviado, nada cobrado. $e->getProblems() lista os números inválidos.
    echo 'Corrija os dados: ' . $e->getMessage() . "\n";
} catch (AuthenticationException $e) {
    echo "Credenciais inválidas ou sem permissão (HTTP {$e->getHttpStatus()}).\n";
} catch (InsufficientBalanceException $e) {
    echo 'Recarregue o saldo (billing->topup).' . "\n";
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter() ?? 5);           // e tente de novo mais tarde
} catch (NotFoundException $e) {
    echo "Não existe.\n";
} catch (ConflictException $e) {
    echo "Estado em conflito: {$e->getErrorCode()}\n";
} catch (ValidationException $e) {
    echo "A API recusou os dados: {$e->getMessage()}\n";
} catch (ServerException $e) {
    // 5xx. Num ENVIO o SMS pode já estar registado/cobrado: consulte sms->history() antes de repetir.
    echo "Erro do servidor ({$e->getHttpStatus()}). Verifique o histórico antes de reenviar.\n";
} catch (InvalidResponseException $e) {
    echo "Resposta inesperada (proxy/manutenção?): {$e->getMessage()}\n";
} catch (TimeoutException $e) {
    // O pedido PODE ter sido processado. Nunca reenvie às cegas.
    echo "Timeout: confirme no histórico se o SMS saiu.\n";
} catch (ConnectionException $e) {
    if ($e->mayHaveReachedServer()) {
        echo "A ligação caiu a meio: confirme no histórico antes de reenviar.\n";
    } else {
        echo "Sem ligação (nem chegou a sair): pode tentar de novo.\n";   // DNS, ligação recusada
    }
} catch (ApiException $e) {
    echo "Erro da API {$e->getHttpStatus()} [{$e->getErrorCode()}]: {$e->getMessage()}\n";
    // $e->getBody() tem a resposta completa
} catch (MozeSMSException $e) {
    echo 'Erro do SDK: ' . $e->getMessage() . "\n";
}

// Registar os pedidos (nunca inclui o segredo): passe um logger ao criar o Client.
// new Client($key, $secret, ['logger' => fn ($nivel, $msg, $ctx) => error_log("$nivel $msg " . json_encode($ctx))]);
