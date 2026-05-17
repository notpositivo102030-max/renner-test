<?php
require __DIR__ . '/../app/security.php';
security_bootstrap('admin');
security_admin_logout('manual');
header('location:login.php');
exit;