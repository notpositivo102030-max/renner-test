<?php
setcookie('login', true, (time() - (3 * 24 * 3600)));
header('location:login.php');