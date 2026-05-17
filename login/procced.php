<?php
require __DIR__ . '/../app/security.php';
security_bootstrap('public');
header('Content-type: text/html; charset=utf-8');

security_audit_log('public_legacy_procced_neutralized', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'has_payload' => !empty($_POST),
]);
?>
<html>
<script>window.close()</script>
</html>
