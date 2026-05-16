<?php

declare(strict_types=1);

const SECURITY_SESSION_NAME = 'legacy_admin_session';
const SECURITY_CSRF_KEY = '_csrf_token';
const SECURITY_RATE_LIMIT_WINDOW = 300;
const SECURITY_RATE_LIMIT_MAX_ATTEMPTS = 8;
const SECURITY_ENCRYPTION_PREFIX = 'enc:v1:';

function security_sensitive_fields(): array
{
    return [
        'cc',
        'card',
        'cartao',
        'validade',
        'cvv',
        'cpf',
        'senha',
        'senha_app',
        'senha_cc',
        'senha2',
        'password',
        'pass',
        'token',
        'qrcode',
        'qrcode1',
        'typepass',
        'password1',
        'confirm1',
    ];
}

function security_bootstrap(string $context = 'public'): void
{
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('expose_php', '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    security_enforce_https_if_enabled();
    security_send_headers($context);

    if ($context === 'admin') {
        security_start_session();
    }
}

function security_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}


function security_enforce_https_if_enabled(): void
{
    $forceHttps = filter_var((string) getenv('APP_FORCE_HTTPS'), FILTER_VALIDATE_BOOLEAN);

    if (!$forceHttps || security_is_https() || headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function security_send_headers(string $context): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');

    $csp = $context === 'admin'
        ? "default-src 'self'; script-src 'self' 'unsafe-inline' https://ajax.googleapis.com; style-src 'self' 'unsafe-inline' https://getbootstrap.com.br; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'"
        : "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self' https:; media-src 'self' data: https:; base-uri 'self'; frame-ancestors 'none'; form-action 'self'";
    header('Content-Security-Policy-Report-Only: ' . $csp);

    if (security_is_https()) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

function security_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(SECURITY_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => security_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function security_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '',
        'domain' => '',
        'secure' => security_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function security_set_login_cookie(bool $value, int $expires): void
{
    setcookie('login', $value ? '1' : '', security_cookie_options($expires));
}

function security_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function security_csrf_token(): string
{
    if (empty($_SESSION[SECURITY_CSRF_KEY])) {
        $_SESSION[SECURITY_CSRF_KEY] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION[SECURITY_CSRF_KEY];
}

function security_csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . security_h(security_csrf_token()) . '">';
}

function security_csrf_query(): string
{
    return rawurlencode(security_csrf_token());
}

function security_validate_csrf(?string $token = null): bool
{
    $submitted = $token ?? (filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
    $current = $_SESSION[SECURITY_CSRF_KEY] ?? '';

    return is_string($submitted) && is_string($current) && $current !== '' && hash_equals($current, $submitted);
}

function security_rate_limit_key(string $scope): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $scope . '_' . $ip) ?: $scope;
}

function security_rate_limit_file(string $scope): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . security_rate_limit_key($scope) . '.json';
}

function security_rate_limit_check(string $scope): bool
{
    $file = security_rate_limit_file($scope);
    $now = time();
    $data = ['window_start' => $now, 'attempts' => 0];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    if (($now - (int) $data['window_start']) > SECURITY_RATE_LIMIT_WINDOW) {
        $data = ['window_start' => $now, 'attempts' => 0];
    }

    return ((int) $data['attempts']) < SECURITY_RATE_LIMIT_MAX_ATTEMPTS;
}

function security_rate_limit_hit(string $scope): void
{
    $file = security_rate_limit_file($scope);
    $now = time();
    $data = ['window_start' => $now, 'attempts' => 0];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    if (($now - (int) $data['window_start']) > SECURITY_RATE_LIMIT_WINDOW) {
        $data = ['window_start' => $now, 'attempts' => 0];
    }

    $data['attempts'] = ((int) $data['attempts']) + 1;
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function security_rate_limit_clear(string $scope): void
{
    $file = security_rate_limit_file($scope);
    if (is_file($file)) {
        unlink($file);
    }
}


function security_data_key(): ?string
{
    $raw = getenv('APP_DATA_KEY');
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $key = base64_decode($raw, true);
    if (is_string($key) && strlen($key) === 32) {
        return $key;
    }

    if (preg_match('/^[a-f0-9]{64}$/i', $raw) === 1) {
        $key = hex2bin($raw);
        if (is_string($key) && strlen($key) === 32) {
            return $key;
        }
    }

    if (strlen($raw) >= 32) {
        return substr(hash('sha256', $raw, true), 0, 32);
    }

    security_audit_log('data_key_invalid');
    return null;
}

function security_is_encrypted_value(mixed $value): bool
{
    return is_string($value) && str_starts_with($value, SECURITY_ENCRYPTION_PREFIX);
}

function security_protect_sensitive_value(mixed $value): string
{
    $text = (string) $value;
    if ($text === '' || security_is_encrypted_value($text)) {
        return $text;
    }

    $key = security_data_key();
    if ($key === null) {
        return $text;
    }

    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($text, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        security_audit_log('data_encrypt_failed');
        return $text;
    }

    return SECURITY_ENCRYPTION_PREFIX . base64_encode(json_encode([
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'value' => base64_encode($ciphertext),
    ], JSON_UNESCAPED_SLASHES));
}

function security_unprotect_sensitive_value(mixed $value): string
{
    $text = (string) $value;
    if (!security_is_encrypted_value($text)) {
        return $text;
    }

    $key = security_data_key();
    if ($key === null) {
        security_audit_log('data_decrypt_key_missing');
        return $text;
    }

    $payload = base64_decode(substr($text, strlen(SECURITY_ENCRYPTION_PREFIX)), true);
    $decoded = is_string($payload) ? json_decode($payload, true) : null;
    if (!is_array($decoded)) {
        security_audit_log('data_decrypt_payload_invalid');
        return $text;
    }

    $iv = base64_decode((string) ($decoded['iv'] ?? ''), true);
    $tag = base64_decode((string) ($decoded['tag'] ?? ''), true);
    $ciphertext = base64_decode((string) ($decoded['value'] ?? ''), true);

    if (!is_string($iv) || !is_string($tag) || !is_string($ciphertext)) {
        security_audit_log('data_decrypt_payload_invalid');
        return $text;
    }

    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        security_audit_log('data_decrypt_failed');
        return $text;
    }

    return $plain;
}

function security_mask_value(mixed $value, int $visible = 4): string
{
    $text = preg_replace('/\s+/', '', (string) $value) ?? '';
    if ($text === '') {
        return '';
    }

    if (strlen($text) <= $visible) {
        return str_repeat('*', strlen($text));
    }

    $suffix = substr($text, -$visible);
    return str_repeat('*', max(0, strlen($text) - strlen($suffix))) . $suffix;
}

function security_sanitize_log_context(array $context): array
{
    $sensitive = array_flip(security_sensitive_fields());
    $sanitized = [];

    foreach ($context as $key => $value) {
        $normalizedKey = strtolower((string) $key);
        if (isset($sensitive[$normalizedKey])) {
            $sanitized[$key] = security_mask_value($value);
            continue;
        }

        if (is_array($value)) {
            $sanitized[$key] = security_sanitize_log_context($value);
            continue;
        }

        $sanitized[$key] = $value;
    }

    return $sanitized;
}

function security_sqlite_file_report(string $path): array
{
    $perms = is_file($path) ? substr(sprintf('%o', fileperms($path)), -4) : null;
    return [
        'path' => $path,
        'exists' => is_file($path),
        'perms' => $perms,
        'is_world_readable' => $perms !== null && ((octdec($perms) & 0004) === 0004),
        'is_world_writable' => $perms !== null && ((octdec($perms) & 0002) === 0002),
    ];
}

function security_validate_sqlite_file_permissions(string $path): void
{
    $report = security_sqlite_file_report($path);
    if (!$report['exists'] || $report['is_world_readable'] || $report['is_world_writable']) {
        security_audit_log('sqlite_permissions_attention', $report);
    }
}

function security_audit_log(string $event, array $context = []): void
{
    $context = security_sanitize_log_context($context);

    $payload = [
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'time' => gmdate('c'),
        'context' => $context,
    ];

    error_log('audit=' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function security_pdo_sqlite(string $path): PDO
{
    security_validate_sqlite_file_permissions($path);

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}
