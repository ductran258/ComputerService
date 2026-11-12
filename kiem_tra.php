<?php

session_start();

if (!isset($_SESSION['dem'])) {
    $_SESSION['dem'] = 0;
}

$_SESSION['dem']++;

echo '<h2>KIEM TRA SESSION</h2>';

echo 'So lan tai trang: ' . $_SESSION['dem'];

echo '<br>';

echo 'Session ID: ' . session_id();

echo '<br>';

echo 'Cookie PHPSESSID: ' . (
    $_COOKIE['PHPSESSID'] ?? 'Khong co cookie'
);