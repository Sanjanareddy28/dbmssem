<?php
session_start(); // Start session to track user state

// Database configuration
$servername = "localhost";
$username = "root";
$password = "Sanjana@28";
$dbname = "air";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create flights table if not exists
$sql = "CREATE TABLE IF NOT EXISTS flights (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flight_number VARCHAR(10) NOT NULL,
    airline VARCHAR(50) NOT NULL,
    origin VARCHAR(50) NOT NULL,
    destination VARCHAR(50) NOT NULL,
    departure_date DATE NOT NULL,
    departure_time TIME NOT NULL,
    arrival_date DATE NOT NULL,
    arrival_time TIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    seats_available INT(3) NOT NULL
)";

if ($conn->query($sql) === FALSE) {
    die("Error creating table: " . $conn->error);
}

// Check if flights table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM flights");
$row = $result->fetch_assoc();

// If table is empty, insert sample data
if ($row['count'] == 0) {
    // Array of popular Indian cities
    $cities = [
        'New Delhi (DEL)', 
        'Mumbai (BOM)', 
        'Bangalore (BLR)', 
        'Chennai (MAA)', 
        'Kolkata (CCU)',
        'Hyderabad (HYD)', 
        'Ahmedabad (AMD)', 
        'Kochi (COK)', 
        'Goa (GOI)', 
        'Jaipur (JAI)'
    ];
    
    // Array of airlines
    $airlines = [
        'Air India', 
        'IndiGo', 
        'SpiceJet', 
        'Vistara', 
        'GoAir',
        'AirAsia India', 
        'Alliance Air'
    ];
    
    // Generate 20 sample flights
    $sql = "INSERT INTO flights (flight_number, airline, origin, destination, departure_date, departure_time, arrival_date, arrival_time, price, seats_available) VALUES ";
    
    $values = [];
    for ($i = 1; $i <= 20; $i++) {
        // Generate random origin and destination (ensuring they're different)
        $origin_index = array_rand($cities);
        $origin = $cities[$origin_index];
        
        do {
            $destination_index = array_rand($cities);
        } while ($destination_index == $origin_index);
        
        $destination = $cities[$destination_index];
        
        // Generate random airline
        $airline = $airlines[array_rand($airlines)];
        
        // Generate flight number
        $flight_number = substr($airline, 0, 2) . rand(1000, 9999);
        
        // Generate random dates within the next 30 days
        $days_ahead = rand(1, 30);
        $departure_date = date('Y-m-d', strtotime("+$days_ahead days"));
        
        // For simplicity, arrival is same day
        $arrival_date = $departure_date;
        
        // Generate random times
        $departure_hour = rand(0, 22);
        $departure_minute = rand(0, 59);
        $departure_time = sprintf("%02d:%02d:00", $departure_hour, $departure_minute);
        
        // Flight duration between 1-5 hours
        $duration_hours = rand(1, 5);
        $arrival_hour = ($departure_hour + $duration_hours) % 24;
        $arrival_minute = rand(0, 59);
        $arrival_time = sprintf("%02d:%02d:00", $arrival_hour, $arrival_minute);
        
        // If arrival hour is less than departure hour, flight arrives next day
        if ($arrival_hour < $departure_hour) {
            $arrival_date = date('Y-m-d', strtotime($departure_date . " +1 day"));
        }
        
        // Generate random price between ₹2500 and ₹15000
        $price = rand(2500, 15000);
        
        // Generate random number of available seats
        $seats_available = rand(5, 150);
        
        $values[] = "('" . $flight_number . "', '" . $airline . "', '" . $origin . "', '" . $destination . "', '" . 
                    $departure_date . "', '" . $departure_time . "', '" . $arrival_date . "', '" . 
                    $arrival_time . "', " . $price . ", " . $seats_available . ")";
    }
    
    $sql .= implode(", ", $values);
    
    if ($conn->query($sql) === FALSE) {
        die("Error inserting data: " . $conn->error);
    }
}

// Function to get all unique origins
function getOrigins($conn) {
    $sql = "SELECT DISTINCT origin FROM flights ORDER BY origin";
    $result = $conn->query($sql);
    
    $origins = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $origins[] = $row["origin"];
        }
    }
    
    return $origins;
}

// Function to get all unique destinations
function getDestinations($conn) {
    $sql = "SELECT DISTINCT destination FROM flights ORDER BY destination";
    $result = $conn->query($sql);
    
    $destinations = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $destinations[] = $row["destination"];
        }
    }
    
    return $destinations;
}

// Function to get available dates
function getDates($conn) {
    $sql = "SELECT DISTINCT departure_date FROM flights ORDER BY departure_date";
    $result = $conn->query($sql);
    
    $dates = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $dates[] = $row["departure_date"];
        }
    }
    
    return $dates;
}

// Function to search for flights
function searchFlights($conn, $origin, $destination, $departure_date, $return_date = null) {
    $sql = "SELECT * FROM flights WHERE origin = '$origin' AND destination = '$destination' AND departure_date = '$departure_date' ORDER BY departure_time";
    $result = $conn->query($sql);
    
    $outbound_flights = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $outbound_flights[] = $row;
        }
    }
    
    $return_flights = [];
    if ($return_date) {
        $sql = "SELECT * FROM flights WHERE origin = '$destination' AND destination = '$origin' AND departure_date = '$return_date' ORDER BY departure_time";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $return_flights[] = $row;
            }
        }
    }
    
    return [
        'outbound' => $outbound_flights,
        'return' => $return_flights
    ];
}

// Check if there's an error message to display
$errorMessage = '';
if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear the error message after displaying it
}

// Get all origins, destinations and dates
$origins = getOrigins($conn);
$destinations = getDestinations($conn);
$dates = getDates($conn);

// Handle form submission
$search_results = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $origin = $_POST["origin"];
    $destination = $_POST["destination"];
    $departure_date = $_POST["departure_date"];
    $return_date = isset($_POST["return_date"]) && !empty($_POST["return_date"]) ? $_POST["return_date"] : null;
    
    $search_results = searchFlights($conn, $origin, $destination, $departure_date, $return_date);
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyWay Airlines - Find Flights</title>
   <style>
   @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

:root {
    --primary-color: #1e3a8a;
    --secondary-color: #0f172a;
    --accent-color: #3b82f6;
    --light-blue: #dbeafe;
    --white: #ffffff;
    --light-gray: #f8fafc;
    --medium-gray: #64748b;
    --dark-gray: #334155;
    --gradient-primary: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    --gradient-secondary: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
}

body {
    background: url('https://i.pinimg.com/originals/60/72/4b/60724ba667f4214a7de2f49dd3c523ac.jpg') center center;
    background-size: cover;
    background-attachment: fixed;
    background-repeat: no-repeat;
    color: var(--secondary-color);
    line-height: 1.6;
    min-height: 100vh;
    position: relative;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

header {
    position: relative;
    padding: 40px 0;
    text-align: center;
}


.logo {
    margin-bottom: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 30px 60px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 25px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.3);
    border: 3px solid rgba(59, 130, 246, 0.3);
    max-width: fit-content;
    margin: 0 auto 40px auto;
}
.logo-text {
    font-size: 6rem; /* Increased by 10x from 0.6rem base */
    font-weight: 900;
    color: #1e3a8a; /* Primary blue color for better contrast on white background */
    text-decoration: none;
    text-shadow: 
        3px 3px 6px rgba(0,0,0,0.3), 
        1px 1px 2px rgba(0,0,0,0.2),
        0 0 15px rgba(59, 130, 246, 0.4); /* Blue glow effect */
    letter-spacing: 8px; /* Increased spacing */
    display: inline-flex;
    align-items: center;
    gap: 30px;
    background: linear-gradient(45deg, #1e3a8a, #3b82f6, #1e40af, #2563eb);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.4));
    font-family: 'Poppins', 'Arial Black', sans-serif;
    text-transform: uppercase;
    position: relative;
    animation: logoGlow 4s ease-in-out infinite alternate;
    transition: all 0.3s ease;
}
.logo-text:hover {
    transform: scale(1.05);
    letter-spacing: 10px;
}


.flight-emoji {
    font-size: 5rem;
    animation: float 3s ease-in-out infinite;
    filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.5));
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

nav {
    display: flex;
    justify-content: center;
    align-items: center;
}

nav ul {
    display: flex;
    align-items: center;
    gap: 40px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    padding: 20px 50px;
    border-radius: 50px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    justify-content: center;
    list-style: none;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

nav ul li a {
    color: #1e3a8a !important;
    text-decoration: none;
    font-weight: 700;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    padding: 12px 24px;
    border-radius: 30px;
    background: transparent;
    text-transform: uppercase;
    letter-spacing: 1px;
}

nav ul li a:hover {
    color: #ffffff !important;
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.search-container {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 40px;
    margin-top: 40px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.2);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

h1 {
    margin-bottom: 30px;
    color: var(--secondary-color);
    font-size: 2.5rem;
    font-weight: 700;
    text-align: center;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.trip-type {
    margin-bottom: 30px;
    display: flex;
    justify-content: center;
    gap: 30px;
}

.trip-type label {
    cursor: pointer;
    font-weight: 500;
    font-size: 1.1rem;
    color: var(--dark-gray);
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.trip-type label:hover {
    background: var(--light-blue);
    color: var(--primary-color);
}

.trip-type input[type="radio"] {
    width: 20px;
    height: 20px;
    accent-color: var(--accent-color);
}

.search-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

label {
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark-gray);
    font-size: 0.95rem;
}

select, input[type="date"] {
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
    background: var(--white);
}

select:focus, input[type="date"]:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.traveler-options {
    display: flex;
    flex-wrap: wrap;
    gap: 25px;
    margin: 30px 0;
    justify-content: center;
}

.checkbox-group {
    display: flex;
    align-items: center;
    background: var(--light-blue);
    padding: 12px 20px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.checkbox-group:hover {
    background: rgba(59, 130, 246, 0.2);
    transform: translateY(-2px);
}

.checkbox-group input {
    margin-right: 10px;
    width: 18px;
    height: 18px;
    accent-color: var(--accent-color);
}

.checkbox-group label {
    margin-bottom: 0;
    font-weight: 500;
    color: var(--primary-color);
    cursor: pointer;
}

button {
    background: var(--gradient-primary);
    color: white;
    border: none;
    padding: 18px 40px;
    border-radius: 50px;
    cursor: pointer;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 30px auto 0;
    display: block;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    text-transform: uppercase;
    letter-spacing: 1px;
}

button:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
}

.results-container {
    margin-top: 50px;
}

.flight-results h2 {
    margin-bottom: 25px;
    color: var(--white);
    font-size: 2rem;
    font-weight: 700;
    text-align: center;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
}

.flight-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 20px;
    align-items: center;
    border: 1px solid rgba(59, 130, 246, 0.2);
    transition: all 0.3s ease;
}

.flight-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.25);
    border-color: var(--accent-color);
}

.flight-details h3 {
    font-size: 1.2rem;
    margin-bottom: 8px;
    color: var(--primary-color);
    font-weight: 600;
}

.flight-time {
    display: flex;
    align-items: center;
    margin: 15px 0;
}

.departure, .arrival {
    display: flex;
    flex-direction: column;
}

.time {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--secondary-color);
}

.date, .city {
    font-size: 0.9rem;
    color: var(--medium-gray);
    font-weight: 500;
}

.duration {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 0 20px;
}

.duration-line {
    width: 100%;
    height: 3px;
    background: var(--gradient-primary);
    margin: 8px 0;
    position: relative;
    border-radius: 2px;
}

.duration-line::before, .duration-line::after {
    content: "";
    width: 10px;
    height: 10px;
    background: var(--accent-color);
    border-radius: 50%;
    position: absolute;
    top: -3.5px;
    box-shadow: 0 0 0 3px var(--white);
}

.duration-line::before {
    left: -5px;
}

.duration-line::after {
    right: -5px;
}

.duration-text {
    font-size: 0.85rem;
    color: var(--medium-gray);
    font-weight: 500;
}

.price-tag {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--accent-color);
}

.book-btn {
    background: var(--gradient-primary);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.book-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.seats {
    font-size: 0.9rem;
    color: var(--medium-gray);
    margin-top: 8px;
    font-weight: 500;
}

.no-results {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    color: var(--medium-gray);
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
}

footer {
    background: var(--gradient-secondary);
    color: white;
    padding: 40px 0;
    margin-top: 80px;
    position: relative;
    overflow: hidden;
}

footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: -50%;
    width: 200%;
    height: 100%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.03), transparent);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

footer .container {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: space-around;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

footer a {
    color: #fbbf24;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    padding: 8px 16px;
    border-radius: 20px;
}

footer a:hover {
    color: #f59e0b;
    background: rgba(251, 191, 36, 0.1);
    transform: translateY(-2px);
}

.copyright {
    font-size: 0.9rem;
    margin-top: 20px;
    text-align: center;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 300;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .logo-text:hover {
    transform: scale(1.05);
    letter-spacing: 10px;
}
    .flight-emoji {
        font-size: 3rem;
    }
    
    nav ul {
        flex-wrap: wrap;
        gap: 15px;
        padding: 20px;
    }
    
    .flight-card {
        grid-template-columns: 1fr;
        padding: 20px;
    }
    
    .flight-time {
        flex-direction: column;
        gap: 15px;
    }
    
    .duration {
        margin: 15px 0;
    }
    
    .search-form {
        grid-template-columns: 1fr;
    }
    
    .traveler-options {
        justify-content: center;
    }
    
    footer .container {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
  .logo-text:hover {
    transform: scale(1.05);
    letter-spacing: 10px;
}
    
    .flight-emoji {
        font-size: 2.5rem;
    }
    
    h1 {
        font-size: 2rem;
    }
    
    .search-container {
        padding: 25px;
    }
    
    nav ul {
        flex-direction: column;
        gap: 10px;
    }
    
    nav ul li a {
        font-size: 1rem;
    }
}
</style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">SKYWAY AIRLINES</div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="flights.php">Flights</a></li>
                    <li><a href="book.php">Book</a></li>
                    <li><a href="manage.php">Manage Booking</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li><a href="dashboard.php">My Account</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <?php if($errorMessage): ?>
            <div class="alert alert-danger">
                <p><?php echo $errorMessage; ?></p>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <h1>Search for Flights</h1>
            
            <form method="post" action="">
                <div class="trip-type">
                    <label>
                        <input type="radio" name="trip_type" value="one_way" id="one_way" checked> One Way
                    </label>
                    <label>
                        <input type="radio" name="trip_type" value="round_trip" id="round_trip"> Round Trip
                    </label>
                </div>
                
                <div class="search-form">
                    <div class="form-group">
                        <label for="origin">From</label>
                        <select name="origin" id="origin" required>
                            <option value="">Select Origin</option>
                            <?php foreach ($origins as $city): ?>
                                <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="destination">To</label>
                        <select name="destination" id="destination" required>
                            <option value="">Select Destination</option>
                            <?php foreach ($destinations as $city): ?>
                                <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="departure_date">Departure Date</label>
                        <select name="departure_date" id="departure_date" required>
                            <option value="">Select Date</option>
                            <?php foreach ($dates as $date): ?>
                                <option value="<?php echo $date; ?>"><?php echo date('D, d M Y', strtotime($date)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group return-date" style="display: none;">
                        <label for="return_date">Return Date</label>
                        <select name="return_date" id="return_date">
                            <option value="">Select Date</option>
                            <?php foreach ($dates as $date): ?>
                                <option value="<?php echo $date; ?>"><?php echo date('D, d M Y', strtotime($date)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="traveler-options">
                    <div class="checkbox-group">
                        <input type="checkbox" id="student" name="traveler_type[]" value="student">
                        <label for="student">Student</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="armed_forces" name="traveler_type[]" value="armed_forces">
                        <label for="armed_forces">Armed Forces</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="senior_citizen" name="traveler_type[]" value="senior_citizen">
                        <label for="senior_citizen">Senior Citizen</label>
                    </div>
                </div>
                
                <button type="submit">Search Flights</button>
            </form>
        </div>
        
        <?php if ($search_results): ?>
            <div class="results-container">
                <!-- Outbound Flights -->
                <div class="flight-results">
                    <h2>Outbound Flights</h2>
                    
                    <?php if (count($search_results['outbound']) > 0): ?>
                        <?php foreach ($search_results['outbound'] as $flight): ?>
                            <div class="flight-card">
                                <div class="flight-details">
                                    <h3><?php echo $flight['airline']; ?></h3>
                                    <p>Flight <?php echo $flight['flight_number']; ?></p>
                                    
                                    <div class="flight-time">
                                        <div class="departure">
                                            <span class="time"><?php echo date('H:i', strtotime($flight['departure_time'])); ?></span>
                                            <span class="date"><?php echo date('D, d M', strtotime($flight['departure_date'])); ?></span>
                                            <span class="city"><?php echo $flight['origin']; ?></span>
                                        </div>
                                        
                                        <div class="duration">
                                            <div class="duration-line"></div>
                                            <?php
                                                // Calculate duration
                                                $departure = strtotime($flight['departure_date'] . ' ' . $flight['departure_time']);
                                                $arrival = strtotime($flight['arrival_date'] . ' ' . $flight['arrival_time']);
                                                $duration_seconds = $arrival - $departure;
                                                $hours = floor($duration_seconds / 3600);
                                                $minutes = floor(($duration_seconds % 3600) / 60);
                                            ?>
                                            <span class="duration-text"><?php echo $hours; ?>h <?php echo $minutes; ?>m</span>
                                        </div>
                                        
                                        <div class="arrival">
                                            <span class="time"><?php echo date('H:i', strtotime($flight['arrival_time'])); ?></span>
                                            <span class="date"><?php echo date('D, d M', strtotime($flight['arrival_date'])); ?></span>
                                            <span class="city"><?php echo $flight['destination']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="price">
                                    <span class="price-tag">₹<?php echo number_format($flight['price']); ?></span>
                                    <span class="seats"><?php echo $flight['seats_available']; ?> seats left</span>
                                </div>
                                
                                <div>
                                    <?php 
                                    $bookLink = "handle.php?flight_id=" . $flight['id'];
                                    
                                    // If round trip is selected and we have return flights, save selected outbound flight id
                                    if ($_POST['trip_type'] == 'round_trip' && !empty($search_results['return'])) {
                                        $_SESSION['selected_outbound_id'] = $flight['id'];
                                        $bookLink = "#return-section";
                                    }
                                    ?>
                                    
                                    <?php if (!isset($_SESSION['user_id'])): ?>
                                        <!-- Not logged in - redirect to login with return path -->
                                        <a href="login.php?redirect=flights.php" class="book-btn">Login to Book</a>
                                    <?php else: ?>
                                        <!-- Logged in - proceed to handle.php or to return section -->
                                        <a href="<?php echo $bookLink; ?>" class="book-btn" <?php if ($_POST['trip_type'] == 'round_trip' && !empty($search_results['return'])) echo 'onclick="selectOutbound(' . $flight['id'] . ')"'; ?>>
                                            <?php echo ($_POST['trip_type'] == 'round_trip' && !empty($search_results['return'])) ? 'Select Flight' : 'Book Now'; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No outbound flights found for your selected criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Return Flights (if applicable) -->
                <?php if ($search_results['return'] && !empty($_POST["return_date"])): ?>
                    <div class="flight-results" id="return-section">
                        <h2>Return Flights</h2>
                        
                        <?php if (count($search_results['return']) > 0): ?>
                            <?php foreach ($search_results['return'] as $flight): ?>
                                <div class="flight-card">
                                    <div class="flight-details">
                                        <h3><?php echo $flight['airline']; ?></h3>
                                        <p>Flight <?php echo $flight['flight_number']; ?></p>
                                        
                                        <div class="flight-time">
                                            <div class="departure">
                                                <span class="time"><?php echo date('H:i', strtotime($flight['departure_time'])); ?></span>
                                                <span class="date"><?php echo date('D, d M', strtotime($flight['departure_date'])); ?></span>
                                                <span class="city"><?php echo $flight['origin']; ?></span>
                                            </div>
                                            
                                            <div class="duration">
                                                <div class="duration-line"></div>
                                                <?php
                                                    // Calculate duration
                                                    $departure = strtotime($flight['departure_date'] . ' ' . $flight['departure_time']);
                                                    $arrival = strtotime($flight['arrival_date'] . ' ' . $flight['arrival_time']);
                                                    $duration_seconds = $arrival - $departure;
                                                    $hours = floor($duration_seconds / 3600);
                                                    $minutes = floor(($duration_seconds % 3600) / 60);
                                                ?>
                                                <span class="duration-text"><?php echo $hours; ?>h <?php echo $minutes; ?>m</span>
                                            </div>
                                            
                                            <div class="arrival">
                                                <span class="time"><?php echo date('H:i', strtotime($flight['arrival_time'])); ?></span>
                                                <span class="date"><?php echo date('D, d M', strtotime($flight['arrival_date'])); ?></span>
                                                <span class="city"><?php echo $flight['destination']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="price">
                                        <span class="price-tag">₹<?php echo number_format($flight['price']); ?></span>
                                        <span class="seats"><?php echo $flight['seats_available']; ?> seats left</span>
                                    </div>
                                    
                                    <div>
                                        <?php if (!isset($_SESSION['user_id'])): ?>
                                            <!-- Not logged in - redirect to login -->
                                            <a href="login.php?redirect=flights.php" class="book-btn">Login to Book</a>
                                        <?php else: ?>
                                            <!-- For round trip, this will pass both outbound and return flight IDs -->
                                            <a href="handle.php?flight_id=<?php echo $_SESSION['selected_outbound_id']; ?>&return_flight_id=<?php echo $flight['id']; ?>" class="book-btn">Book Now</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-results">
                                <p>No return flights found for your selected criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <div class="footer-links">
                <a href="#">About Us</a> |
                <a href="#">Contact Us</a> |
                <a href="#">Terms & Conditions</a> |
                <a href="#">Privacy Policy</a> |
                <a href="#">FAQs</a>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> SkyWay Airlines. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Show/hide return date based on trip type selection
        document.addEventListener('DOMContentLoaded', function() {
            const oneWayRadio = document.getElementById('one_way');
            const roundTripRadio = document.getElementById('round_trip');
            const returnDateDiv = document.querySelector('.return-date');
            
            function toggleReturnDate() {
                if (roundTripRadio.checked) {
                    returnDateDiv.style.display = 'block';
                    document.getElementById('return_date').setAttribute('required', 'required');
                } else {
                    returnDateDiv.style.display = 'none';
                    document.getElementById('return_date').removeAttribute('required');
                }
            }
            
            // Initial check
            toggleReturnDate();
            
            // Event listeners
            oneWayRadio.addEventListener('change', toggleReturnDate);
            roundTripRadio.addEventListener('change', toggleReturnDate);
            
            // Prevent selecting same origin and destination
            const originSelect = document.getElementById('origin');
            const destinationSelect = document.getElementById('destination');
            
            originSelect.addEventListener('change', function() {
                const selectedOrigin = this.value;
                
                for (let i = 0; i < destinationSelect.options.length; i++) {
                    if (destinationSelect.options[i].value === selectedOrigin) {
                        destinationSelect.options[i].disabled = true;
                    } else {
                        destinationSelect.options[i].disabled = false;
                    }
                }
                
                if (destinationSelect.value === selectedOrigin) {
                    destinationSelect.value = "";
                }
            });
            
            destinationSelect.addEventListener('change', function() {
                const selectedDestination = this.value;
                
                for (let i = 0; i < originSelect.options.length; i++) {
                    if (originSelect.options[i].value === selectedDestination) {
                        originSelect.options[i].disabled = true;
                    } else {
                        originSelect.options[i].disabled = false;
                    }
                }
                
                if (originSelect.value === selectedDestination) {
                    originSelect.value = "";
                }
            });
        });

        // Function to handle outbound flight selection in round trip mode
        function selectOutbound(flightId) {
            document.getElementById('return-section').scrollIntoView({
                behavior: 'smooth'
            });
        }
    </script>
</body>
</html>