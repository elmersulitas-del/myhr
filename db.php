<?php
// db.php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "myhr"; // <-- change to your DB name

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
  die("DB Connection failed: " . $conn->connect_error);
}