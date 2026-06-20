<?php
// Database connection (XAMPP defaults).
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'unisport_reservation';

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    error_log('UniSport DB connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}
$conn->set_charset('utf8mb4');
