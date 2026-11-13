# MozeSMS SDK para PHP

Cliente da API MozeSMS (`https://api.mozesms.com`). Sem dependências além de `ext-curl` e `ext-json`; PHP 7.4+.

## Instalar

Requisitos: PHP 7.4 ou superior, com as extensões `curl` e `json`.

### Com Composer (recomendado)

Na pasta do seu projeto (onde está o `composer.json`; se não existir, corra `composer init` primeiro):

    composer require woomoza/mozesms-php

O pacote está no [Packagist](https://packagist.org/packages/woomoza/mozesms-php). O Composer instala-o em `vendor/` e gera o
autoload. Se o projeto for novo e ainda não tiver `composer.json`, `composer init` cria-o.

No seu código, carregue o autoload do Composer uma vez (frameworks como Laravel ou Symfony já o fazem):

```php
require __DIR__ . '/vendor/autoload.php';
```

### Sem Composer

Descarregue o código e carregue o `autoload.php` incluído:

    git clone --branch v1.0.1 https://github.com/WOOMOZA-LDA/mozesms-sdk-php.git

```php
require '/caminho/para/mozesms-sdk-php/autoload.php';
```

### Primeiro teste

Crie as chaves no painel MozeSMS (secção API) e experimente, sem gastar saldo:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$moze = new \MozeSMS\Client('mk_...', 'sk_...');   // API key e secret do painel
echo $moze->account->balance()['balance'], ' MZN', PHP_EOL;
```

Se isto mostrar o saldo, está tudo ligado. Guarde as chaves em variáveis de ambiente (por exemplo `getenv('MOZESMS_KEY')`),
nunca no código nem no Git.

### Testar pelo terminal

Depois de instalar com Composer, o pacote traz o comando `mozesms` (em `vendor/bin`). Defina as chaves e use-o, sem escrever código:

```bash
# Linux / macOS / Git Bash
export MOZESMS_KEY=mk_...  MOZESMS_SECRET=sk_...
# PowerShell
$env:MOZESMS_KEY = 'mk_...'; $env:MOZESMS_SECRET = 'sk_...'
```

```bash
php vendor/bin/mozesms balance                                   # saldo (não gasta nada)
php vendor/bin/mozesms send 846000000 "Olá da MozeSMS!"          # envia um SMS (gasta saldo)
php vendor/bin/mozesms send 846000000 "Olá" --from=MINHALOJA     # com um sender ID aprovado
php vendor/bin/mozesms send +27821234567 "Hello" --dry-run       # valida e mostra, sem enviar
php vendor/bin/mozesms status <id>                               # estado de um SMS já enviado
php vendor/bin/mozesms history --limit=5                         # últimos SMS
php vendor/bin/mozesms otp 846000000                             # envia um código OTP
php vendor/bin/mozesms otp-check <session_id> 123456             # verifica o código
php vendor/bin/mozesms help
```

Opções: `--from=SENDER` (ou `MOZESMS_SENDER_ID`), `--json` (resposta completa) e `--dry-run`. Pode ler o texto de outro comando com `-`:
`echo "texto" | php vendor/bin/mozesms send 846000000 -`. Códigos de saída: 0 sucesso, 1 erro, 2 uso incorreto.
As chaves vão **só** por variáveis de ambiente (não por argumentos, que ficam no histórico do terminal).
Ordem sugerida para o primeiro teste: `balance` → `send ... --dry-run` → `send` para o seu próprio número.

Sem Composer, o mesmo comando está em `bin/mozesms`: `php bin/mozesms balance`.

### Se o `composer require` não encontrar o pacote

Pode acontecer logo depois de uma versão nova (o Packagist demora alguns minutos) ou com `minimum-stability` diferente de `stable`.
Alternativa direta do GitHub:

    composer config repositories.mozesms vcs https://github.com/WOOMOZA-LDA/mozesms-sdk-php
    composer require woomoza/mozesms-php:^1.0

## Usar

```php
$moze = new \MozeSMS\Client('mk_...', 'sk_...');   // API key + secret do painel

$moze->account->balance();                          // saldo, preço por SMS, estimativa
$sms = $moze->sms->send('846000000', 'Olá!', 'MozeSMS');
$moze->sms->status($sms['id']);

$moze->sms->bulk(['846000000', '847000000'], 'Olá {{nome}}', 'MozeSMS');          // um texto, muitos números
$moze->sms->bulkMessages([['phone' => '846000000', 'message' => 'A'], ...]);        // texto por número
$moze->sms->schedule(['846000000'], 'Lembrete', '2026-12-01 09:00:00', 'MozeSMS');
$moze->sms->history(['status' => 'enviado', 'page' => 1]);

$s = $moze->otp->send('846000000');                 // ['session_id', 'expires_in', ...]
$moze->otp->check($s['session_id'], $codigoDoUtilizador);   // true / false
```

### Exemplos

Em [`examples/`](examples). Defina `MOZESMS_KEY` e `MOZESMS_SECRET` e corra `php examples/<ficheiro>.php`. Os que enviam, pedem ou carregam gastam saldo real.

| Ficheiro | Mostra |
|---|---|
| `quickstart.php` | saldo, um SMS, estado e OTP |
| `sms_send_bulk_schedule.php` | envio simples, lote, texto por pessoa, agendamento, histórico |
| `otp_login.php` | enviar e verificar OTP, com os erros típicos |
| `sender_id_request.php` | pedir sender ID (debita saldo), documentos, estados |
| `topup.php` | métodos de pagamento, simular, recarregar e esperar pela confirmação |
| `contacts_campaign.php` | contactos, grupos, import CSV, campanha e templates |
| `whatsapp.php` | sessão, texto/média/localização, modelos, etiquetas, automações |
| `webhook_setup.php` / `webhook_endpoint.php` | registar o webhook e receber eventos assinados |
| `error_handling.php` | todas as exceções, e quando é seguro repetir |

### Sender ID e recarga (topup)

```php
// Pedir um sender ID: o preço é DEBITADO do saldo no momento do pedido.
$moze->senderIds->request('MINHALOJA', 'alfanumerico', 'transacional', 'Notificações da loja online',
    ['entity_type' => 'empresa', 'entity_name' => 'Minha Loja, Lda']);
$moze->senderIds->uploadDocument($id, 'alvara', '/caminho/alvara.pdf');   // documentos de aprovação
$moze->senderIds->all();                                                  // agrupados: aprovado / pendente / rejeitado

// Recarregar saldo (cria uma recarga PENDENTE; o saldo sobe quando o pagamento é confirmado)
$moze->billing->gateways();                          // métodos ativos, taxas, mínimos
$moze->billing->calculate(500, 'mpesa');             // simula: taxa, total a pagar, créditos
$t = $moze->billing->topup(500, 'mpesa', '846000000');
$moze->billing->transactionStatus($t['transaction_db_id']);   // polling
$moze->billing->uploadProof($t['transaction_db_id'], '/caminho/comprovativo.pdf');   // transferência bancária
```

### Tudo o que o SDK cobre

| Propriedade | Operações |
|---|---|
| `sms` | send, bulk, bulkMessages, status, history, exportHistory, schedule, schedules |
| `otp` | send, verify, check, status, cancel, sessions, stats |
| `account` | balance, profile, updateProfile, plan, tenantPlans, tenantInfo, pricing, apiStatus |
| `senderIds` | request, list, all, withFilters, get, share, setDefault, history, activity, filters/addFilter/updateFilter/filterStats, documents/uploadDocument/downloadDocument/documentRequests |
| `billing` | gateways, calculate, topup (purchase), paypalCapture, transactions, transaction, transactionStatus, uploadProof, reactivate, invoices, downloadInvoice |
| `contacts` | list, create, get, update, import (CSV), bulkAssign |
| `groups` | list, create, get, update, contacts, addContacts |
| `templates` | list, create, get, update |
| `campaigns` | list, export, create, get, update, recipients, addRecipients, send, cancel, report, duplicate |
| `reports` | sms, delivery, summary, byStatus, byGateway, costs |
| `dashboard` | stats, recentMessages |
| `apiLogs` | list, get |
| `notifications` | list, markRead, readAll |
| `birthdays` | config, saveConfig, contacts/createContact/updateContact, upcoming, history, process |
| `webhooks` | list, create, get, update, toggle, test, logs |
| `whatsapp` | sessões (createSession, connect, connectWithCode, qr, qrImage, lookup, settings), envio (sendText, sendTextBulk, sendMedia, sendFile, uploadMedia, sendLocation, sendGroup), messages, messageStatus, grupos (groups, groupInfo, groupMembers, createGroup, updateGroup, add/remove/promote/demoteGroupMembers, leaveGroup), contactos (profile, contacts, syncContacts, syncGroups, block/unblockContact), webhooks da sessão (webhooks, registerWebhook, testWebhook) |
| `whatsapp->templates` | list, create, get, update, toggle, send |
| `whatsapp->labels` | list, create, update, contacts, ofContact, addToContact |
| `whatsapp->campaigns` | list, create, get, start, pause, resume, cancel, report |
| `whatsapp->automations` | list/create/get/update/toggle por tipo (chatbot, keyword, welcome, schedule), addStep, updateStep, runScheduleNow, aiSettings, saveAiSettings, aiConversations, testAi, log |
| `whatsapp->billing` | plans, instances, subscribe, changePlan, configure, usage, invoices, charges |

**Fora do SDK de propósito** (sensíveis ou internos): chaves de API (criar/regenerar), login/registo, administração, white-label,
afiliados, impersonação, helpdesk, IPs permitidos do WhatsApp, confirmação manual de pagamentos e webhooks de entrada dos gateways.

### O SDK nunca apaga

Não existe nenhum método que apague ou cancele dados (sender IDs, documentos, contactos, grupos, templates, campanhas, agendamentos,
webhooks, sessões WhatsApp...), e o `Client` recusa qualquer pedido que não seja GET, POST, PUT ou PATCH (`InvalidRequestException`).
Os SMS enviados também nunca se removem. Para apagar algo, use o painel MozeSMS.
A API Go reforça o mesmo do lado do servidor: `DELETE` com chave de API devolve `403 DELETE_NOT_ALLOWED`.

**Formato das respostas:** operações sobre um item (`get`, `create`, `update`) devolvem o conteúdo de `data`;
listagens devolvem o corpo completo (`data` + `pagination`/`total`).

**Operações que cobram ou gastam saldo** (não são repetidas automaticamente): `sms->send/bulk/schedule`, `senderIds->request`,
`campaigns->send`, `birthdays->process`, `whatsapp->send*`, `createSession`, `whatsapp->templates->send`, `whatsapp->campaigns->start`
e `whatsapp->billing->subscribe`. `billing->topup` cria uma transação nova a cada chamada.

Opções do `Client`: `base_url`, `timeout` (30 s), `connect_timeout` (10 s), `max_retries` (2), `user_agent`, `allowed_countries`, `validate_phones`, `logger`.

## Erros

Todos estendem `MozeSMS\Exception\MozeSMSException`. Os de API têm `getHttpStatus()`, `getErrorCode()` e `getBody()`.

| Exceção | Quando |
|---|---|
| `InvalidRequestException` | validação **local**: nada foi enviado nem cobrado (`getProblems()` lista p.ex. os números inválidos) |
| `AuthenticationException` | 401/403 |
| `InsufficientBalanceException` | 402 (o corpo traz `current_balance` e `required_amount`) |
| `ValidationException` | 400/422 (no OTP, código errado = `WRONG_CODE`) |
| `NotFoundException` | 404 |
| `ConflictException` | 409/410 (OTP já verificado, expirado) |
| `RateLimitException` | 429 (`getRetryAfter()`) |
| `ServerException` | 5xx (num envio o SMS pode já estar registado) |
| `InvalidResponseException` | 2xx mas o corpo não é JSON (proxy, manutenção) |
| `ApiException` | qualquer outro erro da API |
| `ConnectionException` / `TimeoutException` | rede/timeout; `mayHaveReachedServer()` diz se repetir é seguro |
| `SignatureVerificationException` | webhook com assinatura inválida |

Opções extra do `Client`: `validate_phones` (por omissão `true`: números inválidos são recusados localmente, porque a API
os cobra sem enviar), `logger` (`callable($level, $msg, $ctx)`), `allow_insecure`. `http://` só é aceite em localhost.

**Repetições:** só os pedidos GET são repetidos (falha de rede ou 5xx). Os POST **nunca** são repetidos
automaticamente: um envio repetido seria cobrado duas vezes. Se receber `ConnectionException` num envio,
consulte o histórico antes de tentar de novo.

## Webhooks

A MozeSMS assina o corpo com HMAC-SHA256 (hex) no cabeçalho `X-MozeSMS-Signature` (evento em `X-MozeSMS-Event`).
Valide sempre sobre o corpo bruto:

```php
$event = \MozeSMS\WebhookSignature::parse($secret);   // lança exceção se a assinatura não bater
```

Eventos: `sms.sent`, `sms.delivered`, `sms.undelivered`, `sms.failed`, `sms.expired`, `sms.cancelled`,
`sms.undeliverable`, `sms.rejected`, `sms.unknown`. Ver `examples/webhook_endpoint.php`.

## Notas sobre a API

- **Números de qualquer país**, em formato internacional: `+27 82 123 4567`, `0027821234567` ou `27821234567`. O SDK normaliza
  para dígitos E.164 antes de enviar. Atalho: 9 dígitos começados por 8 ou 9 são Moçambique (`846000000` → `258846000000`).
  Números moçambicanos são validados a fundo; os outros países só quanto ao formato E.164 (não há lista de operadores).
  `allowed_countries => ['258', '27']` restringe a países concretos.
- **Atenção: a API de SMS atual só envia para Moçambique.** Para outros países o SDK aceita o número, mas o servidor
  trata-o como inválido (e **cobra-o sem enviar**) até o envio internacional e os preços por país serem ativados no servidor.
  Enquanto isso, use `allowed_countries => ['258']`. O WhatsApp já aceita números internacionais.
- Números inválidos **são cobrados e não enviados** pela API; por isso o SDK recusa-os antes do pedido.
- `send()` pode devolver `status: "pendente"` com `queued: true` quando o gateway está ocupado; a mensagem já está paga e segue.
- Autenticação por cabeçalhos `X-API-Key` + `X-API-Secret`, enviados em todos os pedidos.

## Testes

    php tests/run.php        # transporte falso, nenhum pedido real (precisa de PHP 8 por causa de str_ends_with)
