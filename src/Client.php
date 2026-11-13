<?php

declare(strict_types=1);

namespace MozeSMS;

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
use MozeSMS\Resource\Account;
use MozeSMS\Resource\ApiLogs;
use MozeSMS\Resource\Billing;
use MozeSMS\Resource\Birthdays;
use MozeSMS\Resource\Campaigns;
use MozeSMS\Resource\ContactGroups;
use MozeSMS\Resource\Contacts;
use MozeSMS\Resource\Dashboard;
use MozeSMS\Resource\Notifications;
use MozeSMS\Resource\Otp;
use MozeSMS\Resource\Reports;
use MozeSMS\Resource\SenderIds;
use MozeSMS\Resource\Sms;
use MozeSMS\Resource\Templates;
use MozeSMS\Resource\Webhooks;
use MozeSMS\Resource\WhatsApp;

/**
 * Cliente da API MozeSMS.
 *
 *     $moze = new \MozeSMS\Client('mk_xxx', 'sk_xxx');
 *     $moze->sms->send('846000000', 'Olá!', 'MozeSMS');
 *
 * Opções: base_url, timeout (s), connect_timeout (s), max_retries (só GET: falha de rede e 5xx),
 * user_agent, validate_phones (true), logger (callable(string $level, string $msg, array $ctx)),
 * transport (callable, para testes).
 */
final class Client
{
    public const VERSION = '1.0.0';
    public const DEFAULT_BASE_URL = 'https://api.mozesms.com';

    public Sms $sms;
    public Otp $otp;
    public Account $account;
    public Webhooks $webhooks;
    public SenderIds $senderIds;
    public Billing $billing;
    public Contacts $contacts;
    public ContactGroups $groups;
    public Templates $templates;
    public Campaigns $campaigns;
    public Reports $reports;
    public Dashboard $dashboard;
    public ApiLogs $apiLogs;
    public Notifications $notifications;
    public Birthdays $birthdays;
    public WhatsApp $whatsapp;
    /** @internal */
    public Validator $validator;

    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;
    private int $timeout;
    private int $connectTimeout;
    private int $maxRetries;
    private string $userAgent;
    /** @var callable|null */
    private $transport;
    /** @var callable|null */
    private $logger;

    /** @param array<string,mixed> $options */
    public function __construct(string $apiKey, string $apiSecret, array $options = [])
    {
        $apiKey = trim($apiKey);
        $apiSecret = trim($apiSecret);
        if ($apiKey === '' || $apiSecret === '') {
            throw new InvalidRequestException('apiKey e apiSecret são obrigatórios');
        }
        if (preg_match('/[\r\n]/', $apiKey . $apiSecret)) {
            throw new InvalidRequestException('apiKey/apiSecret contêm quebras de linha (cabeçalho inválido)');
        }
        $baseUrl = rtrim((string) ($options['base_url'] ?? self::DEFAULT_BASE_URL), '/');
        $parts = parse_url($baseUrl);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
        if (!is_array($parts) || empty($parts['host']) || !in_array($scheme, ['https', 'http'], true)) {
            throw new InvalidRequestException("base_url inválida: '$baseUrl'");
        }
        // As credenciais seguem em cada pedido: só se aceita HTTP em máquinas locais.
        $local = in_array($parts['host'], ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme === 'http' && !$local && empty($options['allow_insecure'])) {
            throw new InvalidRequestException('base_url tem de ser https (as credenciais iriam em texto simples)');
        }
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUrl = $baseUrl;
        $this->timeout = max(1, (int) ($options['timeout'] ?? 30));
        $this->connectTimeout = max(1, (int) ($options['connect_timeout'] ?? 10));
        $this->maxRetries = max(0, min(5, (int) ($options['max_retries'] ?? 2)));
        $this->userAgent = (string) ($options['user_agent'] ?? 'mozesms-php/' . self::VERSION);
        $this->transport = $options['transport'] ?? null;
        $this->logger = $options['logger'] ?? null;
        if ($this->transport === null && !function_exists('curl_init')) {
            throw new MozeSMSException('A extensão curl do PHP é necessária');
        }
        $countries = $options['allowed_countries'] ?? null;
        if ($countries !== null && (!is_array($countries) || $countries === [] || array_filter($countries, static fn ($c) => !preg_match('/^[1-9][0-9]{0,3}$/', (string) $c)))) {
            throw new InvalidRequestException("allowed_countries tem de ser uma lista de códigos de país, ex.: ['258', '27']");
        }
        $this->validator = new Validator(($options['validate_phones'] ?? true) !== false, $countries);

        $this->sms = new Sms($this);
        $this->otp = new Otp($this);
        $this->account = new Account($this);
        $this->webhooks = new Webhooks($this);
        $this->senderIds = new SenderIds($this);
        $this->billing = new Billing($this);
        $this->contacts = new Contacts($this);
        $this->groups = new ContactGroups($this);
        $this->templates = new Templates($this);
        $this->campaigns = new Campaigns($this);
        $this->reports = new Reports($this);
        $this->dashboard = new Dashboard($this);
        $this->apiLogs = new ApiLogs($this);
        $this->notifications = new Notifications($this);
        $this->birthdays = new Birthdays($this);
        $this->whatsapp = new WhatsApp($this);
    }

    /** Esconde o segredo de var_dump()/logs de depuração. @return array<string,mixed> */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'apiKey' => substr($this->apiKey, 0, 6) . '…', 'apiSecret' => '***'];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $json
     * @return array<string,mixed> corpo JSON decodificado
     * @throws MozeSMSException
     */
    public function request(string $method, string $path, array $query = [], ?array $json = null, ?array $multipart = null): array
    {
        $res = $this->requestRaw($method, $path, $query, $json, $multipart);
        $data = $res['json'];
        if ($res['status'] >= 400) {
            throw $this->toException($res);
        }
        if ($data === null) {
            // 2xx sem JSON: nunca devolver "vazio" como se fosse sucesso.
            throw new InvalidResponseException(
                'Resposta inesperada da API (HTTP ' . $res['status'] . ', corpo não é JSON): ' . $this->excerpt($res['body']),
                $res['status'],
                'INVALID_RESPONSE',
                []
            );
        }
        if (isset($data['success']) && $data['success'] === false) {
            throw $this->toException($res);
        }
        return $data;
    }

    /**
     * Envio de ficheiros (multipart/form-data).
     *
     * @param array<string,scalar> $fields
     * @param array<string,string> $files campo => caminho local (lido para memória; máx. $maxBytes)
     * @return array<string,mixed>
     * @throws MozeSMSException
     */
    public function upload(string $method, string $path, array $fields, array $files, int $maxBytes = 5 * 1024 * 1024): array
    {
        $boundary = '----mozesms' . bin2hex(random_bytes(12));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"" . $this->quote((string) $name) . "\"\r\n\r\n" . $value . "\r\n";
        }
        foreach ($files as $name => $file) {
            if (!is_string($file) || !is_file($file) || !is_readable($file)) {
                throw new InvalidRequestException("Ficheiro não encontrado ou ilegível: '" . (is_string($file) ? $file : gettype($file)) . "'");
            }
            $size = filesize($file);
            if ($size === false || $size === 0) {
                throw new InvalidRequestException("Ficheiro vazio: '$file'");
            }
            if ($size > $maxBytes) {
                throw new InvalidRequestException("Ficheiro '$file' excede " . (int) ($maxBytes / 1048576) . ' MB');
            }
            $content = file_get_contents($file);
            if ($content === false) {
                throw new InvalidRequestException("Não foi possível ler '$file'");
            }
            $mime = (function_exists('mime_content_type') ? mime_content_type($file) : false) ?: 'application/octet-stream';
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"" . $this->quote((string) $name) . '"; filename="'
                . $this->quote(basename($file)) . "\"\r\nContent-Type: $mime\r\n\r\n" . $content . "\r\n";
        }
        $body .= "--$boundary--\r\n";
        return $this->request($method, $path, [], null, ['body' => $body, 'type' => 'multipart/form-data; boundary=' . $boundary]);
    }

    private function quote(string $s): string
    {
        return str_replace(['"', "\r", "\n"], ['%22', '', ''], $s);
    }

    /**
     * Pedido sem interpretar o corpo como JSON (p.ex. exportação CSV).
     *
     * @param array<string,mixed> $query
     * @throws MozeSMSException
     */
    public function download(string $path, array $query = []): string
    {
        $res = $this->requestRaw('GET', $path, $query, null);
        if ($res['status'] >= 400) {
            throw $this->toException($res);
        }
        return $res['body'];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $json
     * @return array{status:int,body:string,json:?array<string,mixed>,headers:array<string,string>}
     */
    private function requestRaw(string $method, string $path, array $query, ?array $json, ?array $multipart = null): array
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
            // O SDK nunca apaga nada (e a API recusa DELETE com chaves de integração).
            throw new InvalidRequestException("Método $method não é permitido pelo SDK: só GET, POST, PUT e PATCH. Para apagar, use o painel MozeSMS.");
        }
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = [
            'Accept: application/json',
            'User-Agent: ' . $this->userAgent,
            'X-API-Key: ' . $this->apiKey,
            'X-API-Secret: ' . $this->apiSecret,
        ];
        $body = null;
        if ($json !== null) {
            try {
                $body = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidRequestException('Não foi possível codificar o pedido em JSON: ' . $e->getMessage());
            }
            $headers[] = 'Content-Type: application/json';
        } elseif ($multipart !== null) {
            $body = $multipart['body'];
            $headers[] = 'Content-Type: ' . $multipart['type'];
        }

        // Só se repete o que é seguro repetir: GET. Um POST de envio repetido cobraria duas vezes.
        $attempts = $method === 'GET' ? $this->maxRetries + 1 : 1;
        $lastError = null;
        $lastResponse = null;
        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                usleep((int) (200000 * (2 ** ($i - 1))));
            }
            try {
                $r = $this->send($method, $url, $headers, $body);
            } catch (ConnectionException $e) {
                $this->log('warning', 'mozesms: falha de ligação', ['method' => $method, 'path' => $path, 'attempt' => $i + 1, 'error' => $e->getMessage()]);
                $lastError = $e;
                $lastResponse = null;
                continue;
            } catch (MozeSMSException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new ConnectionException('Erro no transporte HTTP: ' . $e->getMessage(), true, null, $e);
            }
            $lastError = null;
            if ($r['status'] >= 500 && $i < $attempts - 1) {
                $lastResponse = $r;
                continue;
            }
            $lastResponse = $r;
            break;
        }
        if ($lastError !== null) {
            throw $lastError;
        }
        if ($lastResponse === null) {
            throw new ConnectionException('Sem resposta da API');
        }
        $r = $lastResponse;
        $decoded = $r['body'] === '' ? null : json_decode($r['body'], true);
        $this->log($r['status'] >= 400 ? 'warning' : 'debug', 'mozesms: resposta', ['method' => $method, 'path' => $path, 'status' => $r['status']]);
        return [
            'status' => $r['status'],
            'body' => $r['body'],
            'json' => is_array($decoded) ? $decoded : null,
            'headers' => array_change_key_case($r['headers'] ?? [], CASE_LOWER),
        ];
    }

    /**
     * @param string[] $headers
     * @return array{status:int,body:string,headers?:array<string,string>}
     */
    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        if ($this->transport !== null) {
            $r = ($this->transport)($method, $url, $headers, $body, $this->timeout);
            if (!is_array($r) || !isset($r['status'], $r['body'])) {
                throw new ConnectionException('Transporte personalizado devolveu um resultado inválido');
            }
            return $r;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new ConnectionException('curl_init falhou', false);
        }
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false, // um redirect reenviaria as credenciais
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$respHeaders): int {
                $pos = strpos($line, ':');
                if ($pos !== false) {
                    $respHeaders[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }
        if ($out === false) {
            // 6 DNS, 7 ligação recusada, 35 TLS, 51/58/60: o pedido não chegou a ser enviado.
            $notSent = in_array($errno, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, 35, 51, 58, 60, 77], true);
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new TimeoutException('A API não respondeu em ' . $this->timeout . 's', true, $errno);
            }
            throw new ConnectionException('Falha de ligação à API: ' . $err . " (curl $errno)", !$notSent, $errno);
        }
        return ['status' => $status, 'body' => (string) $out, 'headers' => $respHeaders];
    }

    /** @param array{status:int,body:string,json:?array<string,mixed>,headers:array<string,string>} $res */
    private function toException(array $res): ApiException
    {
        $status = $res['status'];
        $data = $res['json'] ?? [];
        $code = null;
        $message = null;
        // Formatos da API: {"error":"CODE|texto","message":"..."} ou {"error":{"code","message"}}.
        if (isset($data['error']) && is_array($data['error'])) {
            $code = isset($data['error']['code']) ? (string) $data['error']['code'] : null;
            $message = isset($data['error']['message']) ? (string) $data['error']['message'] : null;
        } elseif (isset($data['error']) && is_string($data['error'])) {
            $code = $data['error'];
            $message = isset($data['message']) && is_string($data['message']) ? $data['message'] : $data['error'];
        } elseif (isset($data['message']) && is_string($data['message'])) {
            $message = $data['message'];
        }
        if ($message === null) {
            $message = $res['body'] !== '' && $res['json'] === null
                ? 'HTTP ' . $status . ': ' . $this->excerpt($res['body'])
                : 'Erro HTTP ' . $status;
        }

        $isBalance = $code === 'INSUFFICIENT_BALANCE' || $code === 'Insufficient balance';
        if ($status === 402 || $isBalance) {
            return new InsufficientBalanceException($message, $status, $code, $data);
        }
        if ($status === 401 || $status === 403) {
            return new AuthenticationException($message, $status, $code, $data);
        }
        if ($status === 404) {
            return new NotFoundException($message, $status, $code, $data);
        }
        if ($status === 409 || $status === 410) {
            return new ConflictException($message, $status, $code, $data);
        }
        if ($status === 429) {
            $e = new RateLimitException($message, $status, $code, $data);
            $ra = $res['headers']['retry-after'] ?? null;
            $e->setRetryAfter($ra !== null && ctype_digit($ra) ? (int) $ra : null);
            return $e;
        }
        if ($status === 400 || $status === 422) {
            return new ValidationException($message, $status, $code, $data);
        }
        if ($status >= 500) {
            return new ServerException($message, $status, $code, $data);
        }
        return new ApiException($message, $status, $code, $data);
    }

    private function excerpt(string $body): string
    {
        $t = trim(strip_tags($body));
        $t = (string) preg_replace('/\s+/', ' ', $t);
        return mb_substr($t, 0, 200);
    }

    /** @param array<string,mixed> $ctx */
    private function log(string $level, string $message, array $ctx = []): void
    {
        if ($this->logger === null) {
            return;
        }
        try {
            ($this->logger)($level, $message, $ctx);
        } catch (\Throwable $e) {
            // um logger com defeito nunca pode quebrar um envio
        }
    }
}
