<?php
require __DIR__ . '/../../app/security.php';
security_bootstrap('admin');
security_admin_require('../login.php');

if (!security_validate_csrf($_GET['csrf'] ?? '')) {
	http_response_code(400);
	security_audit_log('admin_delete_csrf_failed', ['id' => $_GET['id'] ?? null]);
	exit('Requisição inválida.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
	http_response_code(400);
	exit('Identificador inválido.');
}

$pdo = security_pdo_sqlite(__DIR__ . '/../../login/db.db');
$sql = "DELETE FROM cc WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
security_audit_log('admin_delete_cc', ['id' => $id, 'user' => security_admin_current_user()]);

    header('Location: ../index.php');
    exit;
