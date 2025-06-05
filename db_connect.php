<?php
// Database connection settings
$servername = "localhost";
$username = "root"; // Replace with your actual database username
$password = "Sanjana@28"; // Replace with your actual database password
$dbname = "air";      // Replace with your actual database name

try {
    // Create PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // If connection fails, display error message
    die("Connection failed: " . $e->getMessage());
}
?>