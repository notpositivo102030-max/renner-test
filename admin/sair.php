<?php
require __DIR__ . '/../app/security.php';
security_bootstrap('admin');
security_audit_log('admin_logout');
security_set_login_cookie(false, (time() - (3 * 24 * 3600)));
header('location:login.php');
exit;