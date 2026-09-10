<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    session_unset();
    session_destroy();
}
redirect('login.php');
