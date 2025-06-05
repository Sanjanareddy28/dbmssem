<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=flights.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "Sanjana@28";
$dbname = "air";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify if flight selection is coming from flights.php
if (isset($_GET['flight_id'])) {
    // Store selected flight details in session
    $flight_id = $_GET['flight_id'];
    
    // Get flight details
    $stmt = $conn->prepare("SELECT * FROM flights WHERE id = ?");
    $stmt->bind_param("i", $flight_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $flight = $result->fetch_assoc();
        $_SESSION['selected_flight'] = $flight;
        $_SESSION['flight_type'] = isset($_GET['return_flight_id']) ? 'Round Trip' : 'One Way';
        
        // If round trip, store return flight details as well
        if (isset($_GET['return_flight_id'])) {
            $return_flight_id = $_GET['return_flight_id'];
            $stmt = $conn->prepare("SELECT * FROM flights WHERE id = ?");
            $stmt->bind_param("i", $return_flight_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $return_flight = $result->fetch_assoc();
                $_SESSION['selected_return_flight'] = $return_flight;
            }
        }
        
        // Redirect to booking page
        header("Location: book.php");
        exit();
    } else {
        // Flight not found
        $_SESSION['error_message'] = "Flight not found. Please try again.";
        header("Location: flights.php");
        exit();
    }
} else {
    // No flight selected
    $_SESSION['error_message'] = "Please select a flight first.";
    header("Location: flights.php");
    exit();
}

$conn->close();
?>