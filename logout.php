<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
session_unset();
session_destroy();
header('Location: /index.php');
exit;
