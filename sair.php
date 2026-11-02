<?php
require_once __DIR__ . '/inc/funcoes.php';

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
