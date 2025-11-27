<?php
// db.php — railway MySQL config

$DB_HOST = "ballast.proxy.rlwy.net";
$DB_USER = "root";
$DB_PASS = "UALGGaxYcSxFIbVTCeuNSBAOApciljsN";
$DB_NAME = "railway";
$DB_PORT = 42159;

// Validate
if (!$DB_HOST || !$DB_USER || !$DB_NAME || !$DB_PORT) {
    error_log("Missing DB config");
    http_response_code(500);
    die("Server DB config error.");
}

// Create connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

// Check connection
if ($conn->connect_error) {
    error_log("DB Connect Error: " . $conn->connect_error);
    die("Database connection failed.");
}
?>
