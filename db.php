<?php
// Load DB credentials from environment variables
$DB_HOST = getenv('DB_HOST');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
$DB_NAME = getenv('DB_NAME');
$DB_PORT = getenv('DB_PORT'); // IMPORTANT: ADD THIS

if (!$DB_HOST || !$DB_USER || !$DB_NAME || !$DB_PORT) {
    error_log("Missing DB environment variables");
    http_response_code(500);
    die("Database configuration error.");
}

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if ($conn->connect_error) {
    error_log("DB Connection Error: " . $conn->connect_error);
    http_response_code(500);
    die("Database connection failed.");
}
?>
