<?php
require __DIR__ . '/inc/lib.php';
$_SESSION = []; session_destroy(); header('Location: login.php');
