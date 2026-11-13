<?php

declare(strict_types=1);

// php tests/run.php  — sem phpunit, com transporte falso (não faz pedidos reais).
require __DIR__ . '/../autoload.php';

use MozeSMS\Client;
use MozeSMS\Exception;
use MozeSMS\Exception\AuthenticationException;
use MozeSMS\Exception\ConnectionException;
use MozeSMS\Exception\InsufficientBalanceException;
use MozeSMS\Exception\ValidationException;
use MozeSMS\WebhookSignature;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? "ok   " : "FAIL ") . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}

/** @param array<int,array{int,string}> $responses */
function fake(array &$calls, array $responses): Client
{
    $i = 0;
    return new Client('mk_test', 'sk_test', ['max_retries' => 1, 'transport' => function ($m, $u, $h, $b) use (&$calls, &$i, $responses) {
        $calls[] = ['method' => $m, 'url' => $u, 'headers' => $h, 'body' => $b];
        $r = $responses[min($i++, count($responses) - 1)];
        if ($r instanceof Throwable) {
            throw $r;
        }
        return ['status' => $r[0], 'body' => $r[1]];
    }]);
}

// send: corpo, cabeçalhos e extração de data
$calls = [];
$c = fake($calls, [[200, '{"success":true,"data":{"id":"abc","status":"enviado","cost":1.35}}']]);
$r = $c->sms->send('846000000', 'Olá', 'MozeSMS');
check('send devolve data', $r['id'] === 'abc' && $r['cost'] === 1.35);
check('send POST /sms/send', $calls[0]['method'] === 'POST' && $calls[0]['url'] === 'https://api.mozesms.com/sms/send');
check('send corpo', json_decode($calls[0]['body'], true) === ['phone' => '258846000000', 'message' => 'Olá', 'sender_id' => 'MozeSMS']);
check('send cabeçalhos de auth', in_array('X-API-Key: mk_test', $calls[0]['headers'], true) && in_array('X-API-Secret: sk_test', $calls[0]['headers'], true));
check('send não repete POST', count($calls) === 1);

// erros mapeados
$calls = [];
$c = fake($calls, [[402, '{"success":false,"error":"Insufficient balance","current_balance":0.5}']]);
try { $c->sms->send('846000000', 'x'); check('402', false); } catch (InsufficientBalanceException $e) { check('402 → InsufficientBalance', $e->getBody()['current_balance'] === 0.5); }

$c = fake($calls, [[401, '{"success":false,"error":{"code":"AUTH_FAILED","message":"Invalid credentials"}}']]);
try { $c->account->balance(); check('401', false); } catch (AuthenticationException $e) { check('401 → Authentication (formato v1)', $e->getErrorCode() === 'AUTH_FAILED' && $e->getMessage() === 'Invalid credentials'); }

// OTP
$c = fake($calls, [[422, '{"success":false,"error":"WRONG_CODE","message":"Código incorrecto"}']]);
check('otp->check false em WRONG_CODE', $c->otp->check('s1', '000000') === false);
$c = fake($calls, [[410, '{"success":false,"error":"EXPIRED","message":"expirou"}']]);
try { $c->otp->check('s1', '1'); check('otp expired', false); } catch (Throwable $e) { check('otp->check propaga EXPIRED', $e->getErrorCode() === 'EXPIRED'); }
$c = fake($calls, [[200, '{"success":true,"message":"ok"}']]);
check('otp->check true', $c->otp->check('s1', '123456') === true);

// GET repete em 5xx e falha de rede; querystring ignora nulos
$calls = [];
$c = fake($calls, [[503, 'down'], [200, '{"success":true,"data":{"balance":10}}']]);
check('GET repete após 5xx', $c->account->balance()['balance'] === 10 && count($calls) === 2);
$calls = [];
$c = fake($calls, [new ConnectionException('x'), [200, '{"success":true,"data":{"balance":1}}']]);
check('GET repete após falha de rede', $c->account->balance()['balance'] === 1 && count($calls) === 2);
$calls = [];
$c = fake($calls, [[200, '{"success":true,"data":[]}']]);
$c->sms->history(['status' => 'enviado', 'search' => null, 'page' => 2]);
check('query sem nulos', str_ends_with($calls[0]['url'], '/sms/history?status=enviado&page=2'));

// 5xx de POST não é repetido nem engolido
$calls = [];
$c = fake($calls, [[502, '{"success":false,"error":"Gateway send failed"}']]);
try { $c->sms->send('846000000', 'x'); check('502', false); } catch (ValidationException $e) { check('502', false); } catch (Throwable $e) { check('502 → ApiException, 1 tentativa', count($calls) === 1 && $e->getHttpStatus() === 502); }

// webhooks
$body = '{"event":"sms.delivered"}';
$sig = hash_hmac('sha256', $body, 's3cret');
check('assinatura válida', WebhookSignature::verify($body, $sig, 's3cret'));
check('assinatura maiúscula aceite', WebhookSignature::verify($body, strtoupper($sig), 's3cret'));
check('assinatura errada', !WebhookSignature::verify($body, $sig, 'outro'));
check('corpo alterado', !WebhookSignature::verify($body . ' ', $sig, 's3cret'));
check('sem assinatura', !WebhookSignature::verify($body, null, 's3cret'));
check('constructEvent', WebhookSignature::constructEvent($body, $sig, 's3cret')['event'] === 'sms.delivered');

// --- validação local: nada chega à rede ---
function throwsA(string $class, callable $fn): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}
$calls = [];
$c = fake($calls, [[200, '{"success":true,"data":{}}']]);
check('número inválido recusado', throwsA(Exception\InvalidRequestException::class, fn () => $c->sms->send('123', 'x')));
check('mensagem vazia recusada', throwsA(Exception\InvalidRequestException::class, fn () => $c->sms->send('846000000', '  ')));
check('UTF-8 inválido recusado', throwsA(Exception\InvalidRequestException::class, fn () => $c->sms->send('846000000', "\xff\xfe")));
check('sender longo recusado', throwsA(Exception\InvalidRequestException::class, fn () => $c->sms->send('846000000', 'x', str_repeat('a', 51))));
check('bulk com inválido recusado', throwsA(Exception\InvalidRequestException::class, fn () => $c->sms->bulk(['846000000', '12'], 'x')));
check('bulk vazio recusado', throwsA(Exception\InvalidRequestException::class, fn () => $c->sms->bulk([], 'x')));
check('schedule repeat inválido', throwsA(Exception\InvalidRequestException::class, fn () => $c->sms->schedule(['846000000'], 'x', '2030-01-01 10:00:00', null, 'hourly')));
check('webhook evento inválido', throwsA(Exception\InvalidRequestException::class, fn () => $c->webhooks->create('n', 'https://a.co/h', ['sms.bad'])));
check('webhook id inválido', throwsA(Exception\InvalidRequestException::class, fn () => $c->webhooks->get(0)));
check('otp code vazio', throwsA(Exception\InvalidRequestException::class, fn () => $c->otp->verify('s', '')));
check('nenhum pedido feito pelas validações', count($calls) === 0);
try {
    $c->sms->bulk(['846000000', 'abc', ''], 'x');
} catch (Exception\InvalidRequestException $e) {
    check('getProblems lista inválidos', $e->getProblems() === ['abc', '']);
}
check('Phone::normalize', \MozeSMS\Phone::normalize('+258 84 600 0000') === '258846000000' && \MozeSMS\Phone::normalize('12345') === null);
check('credenciais vazias', throwsA(Exception\InvalidRequestException::class, fn () => new Client('', 'x')));
check('base_url http remota recusada', throwsA(Exception\InvalidRequestException::class, fn () => new Client('a', 'b', ['base_url' => 'http://example.com'])));
check('base_url localhost http aceite', (new Client('a', 'b', ['base_url' => 'http://localhost:8081'])) instanceof Client);
check('debugInfo não revela segredo', strpos(print_r((new Client('mk_abcdef123', 'sk_supersecret'))->__debugInfo(), true), 'supersecret') === false);

// --- respostas anómalas ---
$c = fake($calls, [[200, '<html>Manutenção</html>']]);
check('200 com HTML → InvalidResponse', throwsA(Exception\InvalidResponseException::class, fn () => $c->account->balance()));
$c = fake($calls, [[200, '']]);
check('200 vazio → InvalidResponse', throwsA(Exception\InvalidResponseException::class, fn () => $c->sms->send('846000000', 'x')));
$c = fake($calls, [[404, '{"success":false,"error":"SESSION_NOT_FOUND","message":"x"}']]);
check('404 → NotFound', throwsA(Exception\NotFoundException::class, fn () => $c->otp->status('s')));
$c = fake($calls, [[409, '{"success":false,"error":"ALREADY_VERIFIED"}']]);
check('409 → Conflict', throwsA(Exception\ConflictException::class, fn () => $c->otp->verify('s', '1')));
$c = fake($calls, [[502, '<html>Bad gateway</html>']]);
try {
    $c->account->balance();
} catch (Throwable $e) {
    check('5xx HTML → ServerException com excerto', $e instanceof Exception\ServerException && strpos($e->getMessage(), 'Bad gateway') !== false && strpos($e->getMessage(), '<html>') === false);
}
$c = new Client('a', 'b', ['transport' => fn () => ['status' => 429, 'body' => '{"error":"RATE_LIMITED"}', 'headers' => ['Retry-After' => '7']]]);
try {
    $c->otp->send('846000000');
} catch (Exception\RateLimitException $e) {
    check('429 → RateLimit com Retry-After', $e->getRetryAfter() === 7);
}
$c = new Client('a', 'b', ['transport' => function () { throw new RuntimeException('boom'); }]);
try {
    $c->sms->send('846000000', 'x');
} catch (Throwable $e) {
    check('erro do transporte → ConnectionException', $e instanceof ConnectionException && $e->mayHaveReachedServer());
}
$c = new Client('a', 'b', ['max_retries' => 0, 'transport' => function () { throw new Exception\TimeoutException('t'); }]);
check('timeout é ConnectionException', throwsA(ConnectionException::class, fn () => $c->account->balance()));
$c = new Client('a', 'b', ['transport' => fn () => 'lixo']);
check('transporte inválido', throwsA(ConnectionException::class, fn () => $c->account->balance()));
$logged = 0;
$c = new Client('a', 'b', ['logger' => function () use (&$logged) { $logged++; throw new Exception('logger partido'); }, 'transport' => fn () => ['status' => 200, 'body' => '{"success":true,"data":{"balance":1}}']]);
check('logger partido não quebra o pedido', $c->account->balance()['balance'] === 1 && $logged > 0);
check('webhook assinatura inválida → exceção própria', throwsA(Exception\SignatureVerificationException::class, fn () => WebhookSignature::constructEvent('{}', 'x', 's')));
check('webhook corpo não JSON', throwsA(Exception\SignatureVerificationException::class, fn () => WebhookSignature::constructEvent('nao-json', WebhookSignature::sign('nao-json', 's'), 's')));

// --- rotas dos recursos: método + caminho + corpo ---
$route = function (callable $fn) {
    $calls = [];
    $c = new Client('a', 'b', ['transport' => function ($m, $u, $h, $b) use (&$calls) {
        $calls[] = [$m, substr($u, strlen('https://api.mozesms.com')), $b, $h];
        return ['status' => 200, 'body' => '{"success":true,"data":{"ok":1}}'];
    }]);
    $fn($c);
    return $calls[0];
};
$expect = function (string $name, array $call, string $method, string $path, ?array $body = null) {
    $ok = $call[0] === $method && $call[1] === $path && ($body === null || json_decode((string) $call[2], true) === $body);
    check($name, $ok);
    if (!$ok) {
        echo '     obtido: ' . $call[0] . ' ' . $call[1] . ' ' . $call[2] . "\n";
    }
};
$expect('senderIds->request', $route(fn ($c) => $c->senderIds->request('minhaloja', 'alfanumerico', 'transacional', 'Loja online', ['entity_type' => 'empresa'])),
    'POST', '/sender-ids/request', ['sender_id' => 'MINHALOJA', 'tipo' => 'alfanumerico', 'uso' => 'transacional', 'descricao' => 'Loja online', 'entity_type' => 'empresa']);
$expect('senderIds->share', $route(fn ($c) => $c->senderIds->share(7, true)), 'PUT', '/sender-ids/7/share', ['compartilhado' => 1]);
$expect('senderIds->addFilter', $route(fn ($c) => $c->senderIds->addFilter(7, 'keyword_block', 'spam')), 'POST', '/sender-ids/7/filters', ['filter_type' => 'keyword_block', 'filter_value' => 'spam']);
$expect('billing->topup mpesa', $route(fn ($c) => $c->billing->topup(500, 'mpesa', '84 600 0000')), 'POST', '/billing/purchase', ['amount' => 500, 'gateway' => 'mpesa', 'customer_phone' => '258846000000']);
$expect('billing->calculate', $route(fn ($c) => $c->billing->calculate(100, 'paypal')), 'POST', '/billing/calculate', ['amount' => 100, 'gateway' => 'paypal']);
$expect('billing->transactions', $route(fn ($c) => $c->billing->transactions(['status' => 'pending'])), 'GET', '/billing/transactions?status=pending');
$expect('contacts->create', $route(fn ($c) => $c->contacts->create('Ana', '846000000', ['email' => 'a@b.co'])), 'POST', '/contacts', ['nome' => 'Ana', 'telefone' => '846000000', 'email' => 'a@b.co']);
$expect('contacts->bulkAssign', $route(fn ($c) => $c->contacts->bulkAssign([1, 2], [3])), 'POST', '/contacts/bulk-assign', ['contact_ids' => [1, 2], 'group_ids' => [3]]);
$expect('groups->addContacts', $route(fn ($c) => $c->groups->addContacts(4, [9])), 'POST', '/contacts/groups/4/contacts', ['contact_ids' => [9]]);
$expect('templates->create', $route(fn ($c) => $c->templates->create('Boas-vindas', 'Olá', 'marketing')), 'POST', '/templates', ['nome' => 'Boas-vindas', 'mensagem' => 'Olá', 'categoria' => 'marketing']);
$expect('campaigns->addRecipients', $route(fn ($c) => $c->campaigns->addRecipients(3, ['846000000'], [], [2])), 'POST', '/campaigns/3/recipients', ['phones' => ['258846000000'], 'group_ids' => [2]]);
$expect('campaigns->send', $route(fn ($c) => $c->campaigns->send(3)), 'POST', '/campaigns/3/send');
$expect('reports->summary', $route(fn ($c) => $c->reports->summary(['date_from' => '2026-01-01'])), 'GET', '/reports/summary?date_from=2026-01-01');
$expect('notifications->readAll', $route(fn ($c) => $c->notifications->readAll()), 'POST', '/notifications/read-all');
$expect('account->updateProfile', $route(fn ($c) => $c->account->updateProfile(['language' => 'pt'])), 'PUT', '/settings/profile', ['language' => 'pt']);
$expect('whatsapp->sendText', $route(fn ($c) => $c->whatsapp->sendText('s1', '846000000', 'Olá')), 'POST', '/whatsapp/send', ['session_id' => 's1', 'phone' => '258846000000', 'message' => 'Olá', 'type' => 'text']);
$expect('whatsapp->sendMedia pdf', $route(fn ($c) => $c->whatsapp->sendMedia('s1', '846000000', 'pdf', 'https://x.co/a.pdf')), 'POST', '/whatsapp/send/pdf', ['session_id' => 's1', 'phone' => '258846000000', 'document' => 'https://x.co/a.pdf']);
$expect('apiLogs->get', $route(fn ($c) => $c->apiLogs->get(5)), 'GET', '/api-logs/5');

// multipart
$tmp = tempnam(sys_get_temp_dir(), 'moze') . '.pdf';
file_put_contents($tmp, '%PDF-1.4 teste');
$call = $route(fn ($c) => $c->senderIds->uploadDocument(7, 'alvara', $tmp));
check('upload multipart', $call[0] === 'POST' && $call[1] === '/sender-ids/7/documents'
    && strpos($call[2], 'name="tipo_documento"') !== false && strpos($call[2], 'name="document"; filename="') !== false && strpos($call[2], '%PDF-1.4 teste') !== false);
check('upload cabeçalho boundary', (bool) preg_grep('#^Content-Type: multipart/form-data; boundary=----mozesms#', $call[3]));
$call = $route(fn ($c) => $c->billing->uploadProof(9, $tmp));
check('uploadProof PUT campo proof', $call[0] === 'PUT' && $call[1] === '/billing/transactions/9/proof' && strpos($call[2], 'name="proof"') !== false);
check('upload ficheiro inexistente', throwsA(Exception\InvalidRequestException::class, fn () => $c->senderIds->uploadDocument(7, 'alvara', '/nao/existe.pdf')));
check('proof extensão inválida', throwsA(Exception\InvalidRequestException::class, fn () => $c->billing->uploadProof(9, '/x/y.exe')));
unlink($tmp);

// validações dos novos recursos
foreach ([
    'sender curto' => fn () => $c->senderIds->request('AB', 'alfanumerico', 'otp', 'x'),
    'sender com espaço' => fn () => $c->senderIds->request('MINHA LOJA', 'alfanumerico', 'otp', 'x'),
    'sender numérico com letras' => fn () => $c->senderIds->request('12ab', 'numerico', 'otp', 'x'),
    'sender tipo inválido' => fn () => $c->senderIds->request('ABC', 'misto', 'otp', 'x'),
    'sender uso inválido' => fn () => $c->senderIds->request('ABC', 'alfanumerico', 'spam', 'x'),
    'sender sem descrição' => fn () => $c->senderIds->request('ABC', 'alfanumerico', 'otp', ' '),
    'filtro tipo inválido' => fn () => $c->senderIds->addFilter(1, 'x', 'y'),
    'topup gateway inválido' => fn () => $c->billing->topup(100, 'cartao'),
    'topup valor zero' => fn () => $c->billing->topup(0, 'paypal'),
    'topup mpesa sem telefone' => fn () => $c->billing->topup(100, 'mpesa'),
    'topup NaN' => fn () => $c->billing->topup(NAN, 'paypal'),
    'contacto telefone curto' => fn () => $c->contacts->create('Ana', '123'),
    'bulkAssign vazio' => fn () => $c->contacts->bulkAssign([], [1]),
    'bulkAssign id negativo' => fn () => $c->contacts->bulkAssign([-1], [1]),
    'template categoria' => fn () => $c->templates->create('n', 'm', 'spam'),
    'campanha sem destinatários' => fn () => $c->campaigns->addRecipients(1),
    'profile campo proibido' => fn () => $c->account->updateProfile(['password' => 'x']),
    'whatsapp media tipo' => fn () => $c->whatsapp->sendMedia('s', '846000000', 'gif', 'u'),
    'whatsapp coordenadas' => fn () => $c->whatsapp->sendLocation('s', '846000000', 99.0, 0.0),
] as $name => $fn) {
    check("validação: $name", throwsA(Exception\InvalidRequestException::class, $fn));
}

// --- países: números internacionais ---
foreach ([
    ['+27 82 123 4567', '27821234567'], ['0027821234567', '27821234567'], ['27821234567', '27821234567'],
    ['+351 912 345 678', '351912345678'], ['+55 (11) 91234-5678', '5511912345678'], ['+1 202-555-0143', '12025550143'],
    ['846000000', '258846000000'], ['+258 84 600 0000', '258846000000'], ['258846000000', '258846000000'],
] as [$in, $out]) {
    check("Phone::normalize $in", \MozeSMS\Phone::normalize($in) === $out);
}
foreach (['', 'abc', '12345', '+0123456789', '+27+8212345', '07123456789', '+258 12 345 6789', '258741234567', '84600', '++27821234567', '27 82 abc 4567'] as $bad) {
    check("Phone inválido '$bad'", \MozeSMS\Phone::normalize($bad) === null);
}
check('Phone::isMozambican', \MozeSMS\Phone::isMozambican('846000000') && !\MozeSMS\Phone::isMozambican('+27821234567'));
$calls = [];
$c = fake($calls, [[200, '{"success":true,"data":{"id":"x"}}']]);
$c->sms->send('+27 82 123 4567', 'Hello', 'MozeSMS');
check('send internacional segue normalizado', json_decode($calls[0]['body'], true)['phone'] === '27821234567');
$c->sms->bulk(['+27821234567', '846000000', '+351912345678'], 'Olá', 'MozeSMS');
check('bulk misto de países', json_decode($calls[1]['body'], true)['phones'] === ['27821234567', '258846000000', '351912345678']);
$c->sms->bulkMessages([['phone' => '+27821234567', 'message' => 'a'], ['phone' => '846000000', 'message' => 'b']], 'MozeSMS');
check('bulkMessages normaliza e mantém texto', json_decode($calls[2]['body'], true)['messages'] === [['phone' => '27821234567', 'message' => 'a'], ['phone' => '258846000000', 'message' => 'b']]);
$only = new Client('a', 'b', ['allowed_countries' => ['258', '27'], 'transport' => fn () => ['status' => 200, 'body' => '{"success":true,"data":{}}']]);
check('allowed_countries aceita 27', $only->sms->send('+27821234567', 'x') === []);
check('allowed_countries recusa 351', throwsA(Exception\InvalidRequestException::class, fn () => $only->sms->send('+351912345678', 'x')));
check('allowed_countries recusa no bulk', throwsA(Exception\InvalidRequestException::class, fn () => $only->sms->bulk(['+27821234567', '+351912345678'], 'x')));
check('allowed_countries inválido', throwsA(Exception\InvalidRequestException::class, fn () => new Client('a', 'b', ['allowed_countries' => ['abc']])));
$last = null;
$raw = new Client('a', 'b', ['validate_phones' => false, 'transport' => function ($m, $u, $h, $b) use (&$last) {
    $last = $b;
    return ['status' => 200, 'body' => '{"success":true,"data":{}}'];
}]);
$raw->sms->send('qualquer-coisa', 'x');
check('validate_phones=false não altera o número', json_decode((string) $last, true)['phone'] === 'qualquer-coisa');
check('mpesa recusa número estrangeiro', throwsA(Exception\InvalidRequestException::class, fn () => $c->billing->topup(100, 'mpesa', '+27821234567')));
$c->billing->topup(100, 'paypal', '+27821234567');
check('paypal aceita telefone estrangeiro', json_decode(end($calls)['body'], true)['customer_phone'] === '27821234567');

// --- nada de DELETE: o SDK não tem e o Client recusa ---
check('Client recusa DELETE', throwsA(Exception\InvalidRequestException::class, fn () => $c->request('DELETE', '/sender-ids/1')));
check('Client recusa método estranho', throwsA(Exception\InvalidRequestException::class, fn () => $c->request('TRACE', '/status')));
foreach ([
    [$c->senderIds, ['delete', 'deleteDocument', 'deleteFilter']], [$c->sms, ['cancelSchedule', 'delete']],
    [$c->contacts, ['delete']], [$c->groups, ['delete', 'removeContact']], [$c->templates, ['delete']],
    [$c->campaigns, ['delete', 'clearRecipients']], [$c->webhooks, ['delete']], [$c->birthdays, ['deleteContact']],
    [$c->whatsapp, ['deleteSession', 'deleteWebhook', 'resetStorage', 'deleteFile']], [$c->whatsapp->labels, ['delete', 'removeFromContact']],
    [$c->whatsapp->templates, ['delete']], [$c->whatsapp->automations, ['delete', 'deleteStep']], [$c->whatsapp->billing, ['cancelSubscription']],
] as [$resource, $methods]) {
    foreach ($methods as $m) {
        check('sem método ' . get_class($resource) . "::$m", !method_exists($resource, $m));
    }
}
$src = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../src')) as $f) {
    if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
        $src .= file_get_contents($f->getPathname());
    }
}
check("código do SDK nunca usa o método 'DELETE'", strpos($src, "'DELETE'") === false);

// --- WhatsApp: sub-recursos ---
$expect('whatsapp->templates->send', $route(fn ($c) => $c->whatsapp->templates->send(4, 's1', '+27821234567', ['nome' => 'Ana'])), 'POST', '/whatsapp/templates/4/send', ['session_id' => 's1', 'to' => '27821234567', 'variables' => ['nome' => 'Ana']]);
$expect('whatsapp->labels->addToContact', $route(fn ($c) => $c->whatsapp->labels->addToContact(3, [1, 2])), 'POST', '/whatsapp/contacts/3/labels', ['label_ids' => [1, 2]]);
$expect('whatsapp->campaigns->start', $route(fn ($c) => $c->whatsapp->campaigns->start(8)), 'POST', '/whatsapp/campaigns/8/start');
$expect('whatsapp->automations->create', $route(fn ($c) => $c->whatsapp->automations->create('keyword', ['name' => 'Preços', 'session_id' => 's1', 'keywords' => ['preço']])), 'POST', '/whatsapp/automations/keyword', ['name' => 'Preços', 'session_id' => 's1', 'keywords' => ['preço']]);
$expect('whatsapp->automations->runScheduleNow', $route(fn ($c) => $c->whatsapp->automations->runScheduleNow(2)), 'POST', '/whatsapp/automations/schedule/2/run-now');
$expect('whatsapp->automations->saveAiSettings', $route(fn ($c) => $c->whatsapp->automations->saveAiSettings(['is_enabled' => true])), 'PUT', '/whatsapp/automations/ai/settings', ['is_enabled' => true]);
$expect('whatsapp->billing->configure PATCH', $route(fn ($c) => $c->whatsapp->billing->configure(5, ['billing_day' => 10])), 'PATCH', '/whatsapp/billing/instances/5/config', ['billing_day' => 10]);
$expect('whatsapp->billing->usage sessão', $route(fn ($c) => $c->whatsapp->billing->usage('s 1')), 'GET', '/whatsapp/billing/sessions/s%201/usage');
$expect('whatsapp->createGroup', $route(fn ($c) => $c->whatsapp->createGroup('Equipa', ['+27821234567', '846000000'], 'Tópico')), 'POST', '/whatsapp/groups/create', ['name' => 'Equipa', 'members' => ['27821234567', '258846000000'], 'topic' => 'Tópico']);
$expect('whatsapp->promoteGroupMembers', $route(fn ($c) => $c->whatsapp->promoteGroupMembers('123@g.us', ['+27821234567'])), 'POST', '/whatsapp/groups/123%40g.us/members/promote', ['members' => ['27821234567']]);
$expect('whatsapp->blockContact', $route(fn ($c) => $c->whatsapp->blockContact('s1', '+27821234567')), 'POST', '/whatsapp/sessions/s1/block/27821234567');
$expect('whatsapp->registerWebhook', $route(fn ($c) => $c->whatsapp->registerWebhook('s1', 'https://x.co/h', ['message'])), 'POST', '/whatsapp/sessions/s1/webhooks', ['url' => 'https://x.co/h', 'events' => ['message']]);
$tmp = tempnam(sys_get_temp_dir(), 'moze') . '.jpg';
file_put_contents($tmp, 'JPEGDATA');
$call = $route(fn ($c) => $c->whatsapp->sendFile('s1', '+27821234567', $tmp, 'legenda'));
check('whatsapp->sendFile multipart', $call[1] === '/whatsapp/send/upload' && strpos($call[2], 'name="phone"') !== false && strpos($call[2], '27821234567') !== false && strpos($call[2], 'name="file"') !== false);
unlink($tmp);
check('whatsapp: tipo de automação inválido', throwsA(Exception\InvalidRequestException::class, fn () => $c->whatsapp->automations->list('x')));
check('whatsapp: automação sem name', throwsA(Exception\InvalidRequestException::class, fn () => $c->whatsapp->automations->create('welcome', ['session_id' => 's'])));
check('whatsapp: grupo com 1 membro', throwsA(Exception\InvalidRequestException::class, fn () => $c->whatsapp->createGroup('G', ['+27821234567'])));
check('whatsapp: webhook http recusado', throwsA(Exception\InvalidRequestException::class, fn () => $c->whatsapp->registerWebhook('s1', 'http://x.co/h')));

echo $fails === 0 ? "\nTudo OK\n" : "\n$fails falha(s)\n";
exit($fails === 0 ? 0 : 1);
