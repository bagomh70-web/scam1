<?php
// db.php — use environment variables (never hardcode credentials)
$DB_HOST = getenv('DB_HOST');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
$DB_NAME = getenv('DB_NAME');

if (!$DB_HOST || !$DB_USER || !$DB_NAME) {
    error_log("Missing DB env vars");
    http_response_code(500);
    die("Server DB config error.");
}

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    error_log("DB Connect Error: " . $conn->connect_error);
    http_response_code(500);
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
