<?php

declare(strict_types=1);

namespace CronShim;

/**
 * Site-side reporting client for the cron-shim hub.
 *
 * Runs WordPress cron, reports the outcome to the hub, and never changes the
 * process exit code — a hub outage must not break the site's cron.
 *
 * Usage:
 *   SHIM_HUB_URL=... SHIM_SITE_UUID=... SHIM_TOKEN=... php cron-shim.php
 *   php cron-shim.php --env-file=/etc/cron-shim.env
 *   php cron-shim.php --dry-run   # print the JSON payload, send nothing
 */
final class Client
{
    public const VERSION = '1.0.0';

    /** @var callable(array<string, mixed>, array<int, string>, string): int|null */
    public $transport = null;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaults(), $this->fromEnvironment(), $config);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * @param  array<int, string>  $argv
     */
    public function runCli(array $argv): int
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--env-file=')) {
                $this->config = array_merge($this->config, $this->parseEnvFile(substr($arg, 11)));
            }

            if ($arg === '--dry-run') {
                $this->config['dry_run'] = true;
            }
        }

        try {
            return $this->run();
        } catch (\Throwable $exception) {
            $this->log('error: '.$exception->getMessage());

            return 0;
        }
    }

    public function run(): int
    {
        if (! $this->config['dry_run']) {
            $this->flushSpool();
        }

        $execution = $this->executeCron();
        $payload = $this->buildPayload($execution);

        if ($this->config['dry_run']) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

            return 0;
        }

        if ($this->hubConfigured()) {
            if (! $this->deliver($payload)) {
                $this->spool($payload);
            }
        }

        return 0;
    }

    /**
     * @return array{startedAt: string, finishedAt: string, durationMs: int, exitCode: int, output: array<int, string>}
     */
    public function executeCron(): array
    {
        $startedAt = gmdate('Y-m-d\TH:i:s\Z');
        $start = hrtime(true);
        $output = [];
        $exitCode = 0;

        $command = (string) $this->config['cron_command'];

        if ($command !== '') {
            $lines = [];
            $result = 0;
            exec('bash -c '.escapeshellarg($command).' 2>&1', $lines, $result);
            $output = $lines;
            $exitCode = $result;
        }

        return [
            'startedAt' => $startedAt,
            'finishedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'durationMs' => (int) ((hrtime(true) - $start) / 1_000_000),
            'exitCode' => $exitCode,
            'output' => $output,
        ];
    }

    /**
     * @param  array{startedAt: string, finishedAt: string, durationMs: int, exitCode: int, output: array<int, string>}  $execution
     * @return array<string, mixed>
     */
    public function buildPayload(array $execution): array
    {
        $maxEntries = (int) $this->config['max_log_entries'];
        $maxLength = (int) $this->config['max_message_length'];
        $level = $execution['exitCode'] === 0 ? 'info' : 'error';

        $logs = [];

        foreach (array_slice($execution['output'], 0, $maxEntries) as $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            $logs[] = [
                'level' => $level,
                'message' => mb_substr($line, 0, $maxLength),
                'logged_at' => $execution['finishedAt'],
                'context' => [],
            ];
        }

        return [
            'site_uuid' => (string) $this->config['site_uuid'],
            'run' => [
                'run_uuid' => $this->uuid(),
                'started_at' => $execution['startedAt'],
                'finished_at' => $execution['finishedAt'],
                'duration_ms' => $execution['durationMs'],
                'status' => $execution['exitCode'] === 0 ? 'success' : 'failed',
                'exit_code' => $execution['exitCode'],
                'jobs_run' => (int) ($this->config['jobs_run'] ?? 0),
                'jobs_failed' => $execution['exitCode'] === 0 ? 0 : 1,
                'summary' => 'cron-shim run',
            ],
            'logs' => $logs,
            'meta' => [
                'php_version' => PHP_VERSION,
                'shim_version' => self::VERSION,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function deliver(array $payload): bool
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            return false;
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->config['token'],
            'X-Shim-Site: '.$this->config['site_uuid'],
        ];

        if ((string) $this->config['secret'] !== '') {
            $headers[] = 'X-Shim-Signature: sha256='.hash_hmac('sha256', $body, (string) $this->config['secret']);
        }

        $status = $this->transport !== null
            ? ($this->transport)($payload, $headers, $body)
            : $this->httpPost($body, $headers);

        return $status >= 200 && $status < 300;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function spool(array $payload): void
    {
        $dir = (string) $this->config['spool_dir'];

        if (! is_dir($dir) && ! @mkdir($dir, 0770, true) && ! is_dir($dir)) {
            return;
        }

        $this->trimSpool($dir);

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        @file_put_contents($dir.'/'.gmdate('YmdHis').'-'.$this->uuid().'.json', $encoded);
    }

    public function flushSpool(): void
    {
        if (! $this->hubConfigured()) {
            return;
        }

        $dir = (string) $this->config['spool_dir'];

        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir.'/*.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            $json = @file_get_contents($file);

            if ($json === false) {
                continue;
            }

            $payload = json_decode($json, true);

            if (! is_array($payload)) {
                @unlink($file);

                continue;
            }

            if ($this->deliver($payload)) {
                @unlink($file);
            } else {
                break; // keep order; retry on the next run
            }
        }
    }

    private function hubConfigured(): bool
    {
        return (string) $this->config['hub_url'] !== ''
            && (string) $this->config['site_uuid'] !== ''
            && (string) $this->config['token'] !== '';
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function httpPost(string $body, array $headers): int
    {
        $url = rtrim((string) $this->config['hub_url'], '/').'/api/v1/ingest';
        $timeout = max(1, (int) $this->config['timeout']);

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
            ]);
            $result = curl_exec($handle);
            $status = $result === false ? 0 : (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);

            return $status;
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);

        if (@file_get_contents($url, false, $context) === false) {
            return 0;
        }

        $statusLine = $http_response_header[0] ?? '';

        return preg_match('#\s(\d{3})\s#', $statusLine, $matches) ? (int) $matches[1] : 0;
    }

    private function trimSpool(string $dir): void
    {
        $max = max(1, (int) $this->config['max_spool']);
        $files = glob($dir.'/*.json') ?: [];

        if (count($files) < $max) {
            return;
        }

        sort($files);
        $excess = count($files) - $max + 1;

        foreach (array_slice($files, 0, $excess) as $file) {
            @unlink($file);
        }
    }

    private function log(string $message): void
    {
        $file = (string) $this->config['log_file'];

        if ($file === '') {
            return;
        }

        @file_put_contents($file, gmdate('Y-m-d\TH:i:s\Z').' '.$message.PHP_EOL, FILE_APPEND);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseEnvFile(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), "\"'");
        }

        $map = [
            'SHIM_HUB_URL' => 'hub_url',
            'SHIM_SITE_UUID' => 'site_uuid',
            'SHIM_TOKEN' => 'token',
            'SHIM_SECRET' => 'secret',
            'SHIM_TIMEOUT' => 'timeout',
            'SHIM_CRON_COMMAND' => 'cron_command',
            'SHIM_SPOOL_DIR' => 'spool_dir',
            'SHIM_LOG_FILE' => 'log_file',
            'SHIM_MAX_LOG_ENTRIES' => 'max_log_entries',
            'SHIM_MAX_SPOOL' => 'max_spool',
        ];

        $mapped = [];

        foreach ($map as $env => $key) {
            if (array_key_exists($env, $values)) {
                $mapped[$key] = $values[$env];
            }
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'hub_url' => '',
            'site_uuid' => '',
            'token' => '',
            'secret' => '',
            'timeout' => 10,
            'cron_command' => 'wp cron event run --due-now',
            'spool_dir' => sys_get_temp_dir().'/cron-shim-spool',
            'log_file' => '',
            'max_log_entries' => 100,
            'max_message_length' => 5000,
            'max_spool' => 50,
            'dry_run' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromEnvironment(): array
    {
        $map = [
            'hub_url' => 'SHIM_HUB_URL',
            'site_uuid' => 'SHIM_SITE_UUID',
            'token' => 'SHIM_TOKEN',
            'secret' => 'SHIM_SECRET',
            'timeout' => 'SHIM_TIMEOUT',
            'cron_command' => 'SHIM_CRON_COMMAND',
            'spool_dir' => 'SHIM_SPOOL_DIR',
            'log_file' => 'SHIM_LOG_FILE',
            'max_log_entries' => 'SHIM_MAX_LOG_ENTRIES',
            'max_spool' => 'SHIM_MAX_SPOOL',
        ];

        $values = [];

        foreach ($map as $key => $env) {
            $value = getenv($env);

            if ($value !== false && $value !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    exit((new Client)->runCli($argv));
}
