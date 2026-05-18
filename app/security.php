<?php

declare(strict_types=1);

const SECURITY_SESSION_NAME = 'legacy_admin_session';
const SECURITY_CSRF_KEY = '_csrf_token';
const SECURITY_RATE_LIMIT_WINDOW = 300;
const SECURITY_RATE_LIMIT_MAX_ATTEMPTS = 8;
const SECURITY_ENCRYPTION_PREFIX = 'enc:v1:';
const SECURITY_ADMIN_AUTH_KEY = 'admin_authenticated';
const SECURITY_ADMIN_USER_KEY = 'admin_user';
const SECURITY_ADMIN_AUTHENTICATED_AT_KEY = 'admin_authenticated_at';
const SECURITY_ADMIN_LAST_ACTIVITY_KEY = 'admin_last_activity';
const SECURITY_ADMIN_MFA_VERIFIED_AT_KEY = 'admin_mfa_verified_at';
const SECURITY_ADMIN_TIMEOUT_DEFAULT = 1800;

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
        'mfa',
        'otp',
        'totp',
        'authorization',
        'api_key',
        'secret',
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
    header('Origin-Agent-Cluster: ?1');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('X-Download-Options: noopen');

    $csp = $context === 'admin'
        ? "default-src 'self'; script-src 'self' 'unsafe-inline' https://ajax.googleapis.com; style-src 'self' 'unsafe-inline' https://getbootstrap.com.br; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'"
        : "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self' https:; media-src 'self' data: https:; base-uri 'self'; frame-ancestors 'none'; form-action 'self'";
    header('Content-Security-Policy-Report-Only: ' . $csp);

    if (security_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
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


function security_admin_timeout_seconds(): int
{
    $timeout = filter_var(getenv('ADMIN_SESSION_TIMEOUT') ?: null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return is_int($timeout) ? $timeout : SECURITY_ADMIN_TIMEOUT_DEFAULT;
}

function security_admin_is_authenticated(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    if (($_SESSION[SECURITY_ADMIN_AUTH_KEY] ?? false) !== true) {
        return false;
    }

    $user = $_SESSION[SECURITY_ADMIN_USER_KEY] ?? '';
    $lastActivity = (int) ($_SESSION[SECURITY_ADMIN_LAST_ACTIVITY_KEY] ?? 0);
    $authenticatedAt = (int) ($_SESSION[SECURITY_ADMIN_AUTHENTICATED_AT_KEY] ?? 0);
    $mfaVerifiedAt = (int) ($_SESSION[SECURITY_ADMIN_MFA_VERIFIED_AT_KEY] ?? 0);
    $now = time();

    if (!is_string($user) || $user === '' || $lastActivity <= 0 || $authenticatedAt <= 0 || $mfaVerifiedAt < $authenticatedAt) {
        security_admin_clear_session('invalid');
        security_audit_log('admin_session_invalid');
        return false;
    }

    if (($now - $lastActivity) > security_admin_timeout_seconds()) {
        security_admin_clear_session('timeout');
        security_audit_log('admin_session_timeout', ['user' => $user]);
        return false;
    }

    $_SESSION[SECURITY_ADMIN_LAST_ACTIVITY_KEY] = $now;
    return true;
}

function security_admin_login(string $user): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        security_start_session();
    }

    session_regenerate_id(true);
    $_SESSION[SECURITY_ADMIN_AUTH_KEY] = true;
    $_SESSION[SECURITY_ADMIN_USER_KEY] = $user;
    $_SESSION[SECURITY_ADMIN_AUTHENTICATED_AT_KEY] = time();
    $_SESSION[SECURITY_ADMIN_LAST_ACTIVITY_KEY] = time();
    $_SESSION[SECURITY_ADMIN_MFA_VERIFIED_AT_KEY] = time();
    security_set_login_cookie(true, time() + security_admin_timeout_seconds());
}

function security_admin_clear_session(string $reason = 'logout'): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset(
            $_SESSION[SECURITY_ADMIN_AUTH_KEY],
            $_SESSION[SECURITY_ADMIN_USER_KEY],
            $_SESSION[SECURITY_ADMIN_AUTHENTICATED_AT_KEY],
            $_SESSION[SECURITY_ADMIN_LAST_ACTIVITY_KEY],
            $_SESSION[SECURITY_ADMIN_MFA_VERIFIED_AT_KEY]
        );
    }

    security_set_login_cookie(false, time() - 3600);
}

function security_admin_logout(string $reason = 'logout'): void
{
    $user = $_SESSION[SECURITY_ADMIN_USER_KEY] ?? null;
    security_audit_log('admin_logout', ['user' => is_string($user) ? $user : null, 'reason' => $reason]);
    security_admin_clear_session($reason);

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => security_is_https(),
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}

function security_admin_require(string $loginPath = 'login.php'): void
{
    if (security_admin_is_authenticated()) {
        return;
    }

    security_audit_log('admin_access_denied', ['target' => $_SERVER['REQUEST_URI'] ?? null]);
    if (!headers_sent()) {
        header('Location: ' . $loginPath);
    }
    exit;
}

function security_admin_current_user(): ?string
{
    $user = $_SESSION[SECURITY_ADMIN_USER_KEY] ?? null;
    return is_string($user) && $user !== '' ? $user : null;
}

function security_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function security_mask_field(string $field, mixed $value): string
{
    $plain = security_unprotect_sensitive_value($value);
    $normalized = strtolower($field);

    if (in_array($normalized, ['cc', 'cartao', 'card'], true)) {
        return security_mask_value($plain, 4);
    }

    if (in_array($normalized, ['cpf', 'telefone', 'phone'], true)) {
        return security_mask_value($plain, 3);
    }

    if (str_contains($normalized, 'senha') || in_array($normalized, ['cvv', 'token', 'qrcode', 'qrcode1', 'mfa', 'otp', 'totp'], true)) {
        return $plain === '' ? '' : str_repeat('*', min(max(strlen($plain), 3), 12));
    }

    if (str_contains($normalized, 'email')) {
        [$local, $domain] = array_pad(explode('@', $plain, 2), 2, '');
        if ($domain === '') {
            return security_mask_value($plain, 2);
        }
        return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1)) . '@' . $domain;
    }

    return security_mask_value($plain);
}

function security_admin_totp_secret(): string
{
    return strtoupper(preg_replace('/[^A-Z2-7]/', '', (string) getenv('ADMIN_TOTP_SECRET')) ?? '');
}

function security_totp_base32_decode(string $secret): string|false
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
    if ($secret === '') {
        return false;
    }

    $buffer = 0;
    $bitsLeft = 0;
    $output = '';

    for ($i = 0, $length = strlen($secret); $i < $length; $i++) {
        $value = strpos($alphabet, $secret[$i]);
        if ($value === false) {
            return false;
        }

        $buffer = ($buffer << 5) | $value;
        $bitsLeft += 5;

        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xff);
        }
    }

    return $output;
}

function security_totp_code(string $secret, ?int $time = null, int $period = 30, int $digits = 6): ?string
{
    $key = security_totp_base32_decode($secret);
    if ($key === false || strlen($key) < 10) {
        return null;
    }

    $counter = intdiv($time ?? time(), $period);
    $binaryCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binaryCounter, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
    $truncated = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);

    return str_pad((string) ($truncated % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function security_totp_verify(mixed $submitted, string $secret, int $allowedDriftSteps = 1): bool
{
    $code = preg_replace('/\D+/', '', (string) $submitted) ?? '';
    $key = security_totp_base32_decode($secret);
    if ($code === '' || strlen($code) !== 6 || $key === false || strlen($key) < 10) {
        return false;
    }

    $now = time();
    for ($step = -$allowedDriftSteps; $step <= $allowedDriftSteps; $step++) {
        $expected = security_totp_code($secret, $now + ($step * 30));
        if (is_string($expected) && hash_equals($expected, $code)) {
            return true;
        }
    }

    return false;
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
    return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $scope . '_ip_' . $ip) ?: $scope;
}

function security_rate_limit_keys(string $scope): array
{
    $keys = [security_rate_limit_key($scope)];

    if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $sessionPart = substr(hash('sha256', session_id()), 0, 16);
        $sessionKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $scope . '_session_' . $ip . '_' . $sessionPart) ?: $scope;
        $keys[] = $sessionKey;
    }

    return array_values(array_unique($keys));
}

function security_rate_limit_file_for_key(string $key): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $key . '.json';
}

function security_rate_limit_file(string $scope): string
{
    return security_rate_limit_file_for_key(security_rate_limit_key($scope));
}

function security_rate_limit_read(string $file, int $now): array
{
    $data = ['window_start' => $now, 'attempts' => 0];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    if (($now - (int) $data['window_start']) > SECURITY_RATE_LIMIT_WINDOW) {
        return ['window_start' => $now, 'attempts' => 0];
    }

    return $data;
}

function security_rate_limit_check(string $scope): bool
{
    $now = time();

    foreach (security_rate_limit_keys($scope) as $key) {
        $data = security_rate_limit_read(security_rate_limit_file_for_key($key), $now);
        if (((int) $data['attempts']) >= SECURITY_RATE_LIMIT_MAX_ATTEMPTS) {
            return false;
        }
    }

    return true;
}

function security_rate_limit_hit(string $scope): void
{
    $now = time();

    foreach (security_rate_limit_keys($scope) as $key) {
        $file = security_rate_limit_file_for_key($key);
        $data = security_rate_limit_read($file, $now);
        $data['attempts'] = ((int) $data['attempts']) + 1;
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($file, 0600);
    }
}

function security_rate_limit_clear(string $scope): void
{
    foreach (security_rate_limit_keys($scope) as $key) {
        $file = security_rate_limit_file_for_key($key);
        if (is_file($file)) {
            unlink($file);
        }
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
    if (is_array($value) || is_object($value)) {
        return '[redacted]';
    }

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
        $isSensitive = isset($sensitive[$normalizedKey]);
        foreach (array_keys($sensitive) as $sensitiveName) {
            if (!$isSensitive && $sensitiveName !== '' && str_contains($normalizedKey, $sensitiveName)) {
                $isSensitive = true;
            }
        }

        if ($isSensitive) {
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

function security_sqlite_path(): string
{
    $configuredPath = getenv('APP_DB_PATH');

    if ($configuredPath !== false && trim($configuredPath) !== '') {
        return $configuredPath;
    }

    return dirname(__DIR__) . '/login/db.db';
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
