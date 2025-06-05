<?php
// Start session to track user login status
session_start();

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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=ticket.php");
    exit();
}

// Check if we have booking ID in session
if (!isset($_SESSION['booking_id'])) {
    header("Location: dashboard.php");
    exit();
}

$bookingId = $_SESSION['booking_id'];
$userId = $_SESSION['user_id'];

// Get booking details
$bookingQuery = "
    SELECT b.*, p.Transaction_ID, p.Payment_Date, p.Card_Last_Four, p.Card_Name,
           f1.flight_number as outbound_flight_number, f1.airline as outbound_airline,
           f1.origin as outbound_origin, f1.destination as outbound_destination,
           f1.departure_date as outbound_departure_date, f1.departure_time as outbound_departure_time,
           f1.arrival_date as outbound_arrival_date, f1.arrival_time as outbound_arrival_time,
           f2.flight_number as return_flight_number, f2.airline as return_airline,
           f2.origin as return_origin, f2.destination as return_destination,
           f2.departure_date as return_departure_date, f2.departure_time as return_departure_time,
           f2.arrival_date as return_arrival_date, f2.arrival_time as return_arrival_time,
           bc.Email, bc.Phone_Number, bc.Address, bc.City, bc.Country, bc.Postal_Code
    FROM bookings b
    JOIN payments p ON b.Booking_ID = p.Booking_ID
    JOIN flights f1 ON b.Flight_ID = f1.id
    LEFT JOIN flights f2 ON b.Return_Flight_ID = f2.id
    JOIN booking_contact bc ON b.Booking_ID = bc.Booking_ID
    WHERE b.Booking_ID = ? AND b.User_ID = ?
";

if (!($stmt = $conn->prepare($bookingQuery))) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("ii", $bookingId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard.php");
    exit();
}

$booking = $result->fetch_assoc();

// Get passenger details
$passengerQuery = "SELECT * FROM passenger_details WHERE Booking_ID = ?";
$passengerStmt = $conn->prepare($passengerQuery);
$passengerStmt->bind_param("i", $bookingId);
$passengerStmt->execute();
$passengerResult = $passengerStmt->get_result();
$passengers = $passengerResult->fetch_all(MYSQLI_ASSOC);

// Check if we need to add to confirmations table
$confirmationQuery = "SELECT * FROM booking_confirmations WHERE Booking_ID = ?";
$confirmStmt = $conn->prepare($confirmationQuery);
$confirmStmt->bind_param("i", $bookingId);
$confirmStmt->execute();
$confirmResult = $confirmStmt->get_result();

if ($confirmResult->num_rows === 0) {
    // Generate confirmation number
    $confirmationNumber = "SKY" . strtoupper(substr(md5(uniqid()), 0, 10));
    
    // Insert into confirmations table
    $insertConfirmation = $conn->prepare("INSERT INTO booking_confirmations (Booking_ID, Confirmation_Number, Confirmation_Date) VALUES (?, ?, NOW())");
    $insertConfirmation->bind_param("is", $bookingId, $confirmationNumber);
    $insertConfirmation->execute();
} else {
    $confirmation = $confirmResult->fetch_assoc();
    $confirmationNumber = $confirmation['Confirmation_Number'];
}

// Add confirmation number to the booking data
$booking['confirmation_number'] = $confirmationNumber;

// Clear booking ID from session once displayed
unset($_SESSION['booking_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyWay Airlines - Booking Confirmation</title>
    <style>
        :root {
            --primary-color: #0052cc;
            --secondary-color: #f0f8ff;
            --success-color: #d4edda;
            --success-text: #155724;
            --success-border: #c3e6cb;
            --text-color: #333;
            --light-gray: #f8f9fa;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 25px;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        nav ul li a:hover {
            opacity: 0.8;
        }
        
        .success-alert {
            background-color: var(--success-color);
            color: var(--success-text);
            border: 1px solid var(--success-border);
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .main-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin: 20px 0;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0047b3 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .card-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .card-content {
            padding: 40px;
        }
        
        .confirmation-section {
            background: var(--secondary-color);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-bottom: 35px;
            border: 2px solid #e3f2fd;
        }
        
        .confirmation-section h2 {
            color: var(--primary-color);
            font-size: 20px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .confirmation-code {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-color);
            letter-spacing: 3px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
        }
        
        .booking-date {
            color: #666;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .flight-section {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 1px 8px rgba(0,0,0,0.05);
        }
        
        .flight-header {
            background: #f8fafc;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .flight-header h3 {
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .flight-header h3::before {
            content: "✈️";
            margin-right: 12px;
            font-size: 20px;
        }
        
        .flight-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            padding: 25px;
        }
        
        .detail-item {
            text-align: left;
        }
        
        .detail-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .time-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            padding: 25px;
            border-top: 1px dashed var(--border-color);
        }
        
        .passenger-section {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.05);
        }
        
        .section-header {
            background: #f8fafc;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-header h3 {
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
        }
        
        .passenger-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 25px;
        }
        
        .passenger-card {
            background: var(--light-gray);
            padding: 18px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .passenger-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .passenger-type {
            color: #666;
            font-size: 14px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 35px;
        }
        
        .info-section {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.05);
        }
        
        .info-content {
            padding: 25px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
            min-width: 120px;
        }
        
        .info-value {
            font-weight: 600;
            text-align: right;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 35px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            min-width: 150px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0047b3 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,82,204,0.3);
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 25px 0;
            margin-top: 50px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .flight-details-grid {
                grid-template-columns: 1fr;
            }
            
            .time-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .confirmation-code {
                font-size: 24px;
                letter-spacing: 2px;
            }
        }
        
        @media print {
            header, footer, .action-buttons {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .container {
                padding: 0;
                max-width: none;
            }
            
            .main-card {
                box-shadow: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">SkyWay Airlines</div>
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
        <div class="success-alert">
            Your booking has been confirmed! Please check your email for your e-ticket.
        </div>

        <div class="main-card">
            <div class="card-header">
                <h1>E-Ticket / Booking Confirmation</h1>
                <p>SkyWay Airlines - Your journey begins with us</p>
            </div>
            
            <div class="card-content">
                <div class="confirmation-section">
                    <h2>Booking Confirmation</h2>
                    <div class="confirmation-code"><?php echo $booking['confirmation_number']; ?></div>
                    <div class="booking-date">Booking Date: <?php echo date('d M Y H:i', strtotime($booking['Payment_Date'])); ?></div>
                </div>
                
                <div class="flight-section">
                    <div class="flight-header">
                        <h3>Outbound Flight</h3>
                    </div>
                    <div class="flight-details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Flight</div>
                            <div class="detail-value"><?php echo $booking['outbound_flight_number']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Airline</div>
                            <div class="detail-value"><?php echo $booking['outbound_airline']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">From</div>
                            <div class="detail-value"><?php echo $booking['outbound_origin']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">To</div>
                            <div class="detail-value"><?php echo $booking['outbound_destination']; ?></div>
                        </div>
                    </div>
                    <div class="time-info">
                        <div class="detail-item">
                            <div class="detail-label">Departure</div>
                            <div class="detail-value">
                                <?php echo date('d M Y', strtotime($booking['outbound_departure_date'])); ?><br>
                                <?php echo date('H:i', strtotime($booking['outbound_departure_time'])); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Arrival</div>
                            <div class="detail-value">
                                <?php echo date('d M Y', strtotime($booking['outbound_arrival_date'])); ?><br>
                                <?php echo date('H:i', strtotime($booking['outbound_arrival_time'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if(!empty($booking['return_flight_number'])): ?>
                <div class="flight-section">
                    <div class="flight-header">
                        <h3>Return Flight</h3>
                    </div>
                    <div class="flight-details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Flight</div>
                            <div class="detail-value"><?php echo $booking['return_flight_number']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Airline</div>
                            <div class="detail-value"><?php echo $booking['return_airline']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">From</div>
                            <div class="detail-value"><?php echo $booking['return_origin']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">To</div>
                            <div class="detail-value"><?php echo $booking['return_destination']; ?></div>
                        </div>
                    </div>
                    <div class="time-info">
                        <div class="detail-item">
                            <div class="detail-label">Departure</div>
                            <div class="detail-value">
                                <?php echo date('d M Y', strtotime($booking['return_departure_date'])); ?><br>
                                <?php echo date('H:i', strtotime($booking['return_departure_time'])); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Arrival</div>
                            <div class="detail-value">
                                <?php echo date('d M Y', strtotime($booking['return_arrival_date'])); ?><br>
                                <?php echo date('H:i', strtotime($booking['return_arrival_time'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="passenger-section">
                    <div class="section-header">
                        <h3>Passenger Information</h3>
                    </div>
                    <div class="passenger-grid">
                        <?php foreach($passengers as $passenger): ?>
                        <div class="passenger-card">
                            <div class="passenger-name"><?php echo $passenger['First_Name'] . ' ' . $passenger['Last_Name']; ?></div>
                            <div class="passenger-type"><?php echo $passenger['Passenger_Type']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-section">
                        <div class="section-header">
                            <h3>Contact Information</h3>
                        </div>
                        <div class="info-content">
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo $booking['Email']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo $booking['Phone_Number']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Address:</span>
                                <span class="info-value"><?php echo $booking['Address']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">City:</span>
                                <span class="info-value"><?php echo $booking['City']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Country:</span>
                                <span class="info-value"><?php echo $booking['Country']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Postal Code:</span>
                                <span class="info-value"><?php echo $booking['Postal_Code']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="section-header">
                            <h3>Payment Information</h3>
                        </div>
                        <div class="info-content">
                            <div class="info-row">
                                <span class="info-label">Transaction ID:</span>
                                <span class="info-value"><?php echo $booking['Transaction_ID']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Payment Date:</span>
                                <span class="info-value"><?php echo date('d M Y H:i', strtotime($booking['Payment_Date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Card Number:</span>
                                <span class="info-value">XXXX XXXX XXXX <?php echo $booking['Card_Last_Four']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Card Holder:</span>
                                <span class="info-value"><?php echo $booking['Card_Name']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total Amount:</span>
                                <span class="info-value">₹<?php echo number_format($booking['Total_Amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="window.print()">Print Ticket</button>
                    <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2025 SkyWay Airlines. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>