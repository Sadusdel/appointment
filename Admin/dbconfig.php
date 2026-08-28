<?php
require_once __DIR__ . '/csrf_guard.php';

$conn = new mysqli('localhost', 'root', '', 'wt_database');
if ($conn->connect_error) {
    die('Cannot connect to db');
}
$conn->set_charset('utf8mb4');
