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
    header("Location: login.php?redirect=book.php");
    exit();
}

// Check if we have booking data in session
if (!isset($_SESSION['booking_data'])) {
    header("Location: book.php");
    exit();
}

$bookingData = $_SESSION['booking_data'];
$selectedFlight = $_SESSION['selected_flight'];
$flightType = $_SESSION['flight_type'];
$returnFlight = isset($_SESSION['selected_return_flight']) ? $_SESSION['selected_return_flight'] : null;

// Process payment form submission
$paymentSuccess = false;
$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_payment'])) {
    // Get payment details
    $cardNumber = trim($_POST['card_number']);
    $cardName = trim($_POST['card_name']);
    $expiryDate = trim($_POST['expiry_date']);
    $cvv = trim($_POST['cvv']);
    
    // Enhanced validation
    if (strlen($cardNumber) < 12 || empty($cardName) || empty($expiryDate) || strlen($cvv) < 3) {
        $errorMessage = "Please enter valid payment details";
    } else {
        // Validate expiry date format (MM/YY)
        if (!preg_match('/^\d{2}\/\d{2}$/', $expiryDate)) {
            $errorMessage = "Please enter expiry date in MM/YY format";
        } else {
            // Get data from session
            $userId = $_SESSION['user_id'];
            $flightId = $selectedFlight['id'];
            $passengerCount = $bookingData['passenger_count'];
            $totalAmount = $bookingData['total_amount'];
            $returnFlightId = $returnFlight ? $returnFlight['id'] : NULL;
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert into bookings table with Pending status
                $stmt = $conn->prepare("INSERT INTO bookings (User_ID, Flight_ID, Flight_Type, Passenger_Count, Total_Amount, Payment_Status, Booking_Status, Return_Flight_ID) VALUES (?, ?, ?, ?, ?, 'Pending', 'Pending', ?)");
                $stmt->bind_param("iisidi", $userId, $flightId, $flightType, $passengerCount, $totalAmount, $returnFlightId);
                
                if ($stmt->execute()) {
                    $bookingId = $conn->insert_id;
                    
                    // Insert passenger details
                    for ($i = 1; $i <= $passengerCount; $i++) {
                        $firstName = $bookingData["passenger_{$i}_first_name"];
                        $lastName = $bookingData["passenger_{$i}_last_name"];
                        $gender = $bookingData["passenger_{$i}_gender"];
                        $dob = $bookingData["passenger_{$i}_dob"];
                        $passengerType = $bookingData["passenger_{$i}_type"];
                        $passportNumber = $bookingData["passenger_{$i}_passport"];
                        $specialRequest = isset($bookingData["passenger_{$i}_request"]) ? $bookingData["passenger_{$i}_request"] : NULL;
                        
                        $passengerStmt = $conn->prepare("INSERT INTO passenger_details (Booking_ID, First_Name, Last_Name, Gender, Date_of_Birth, Passenger_Type, Passport_Number, Special_Request) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $passengerStmt->bind_param("isssssss", $bookingId, $firstName, $lastName, $gender, $dob, $passengerType, $passportNumber, $specialRequest);
                        $passengerStmt->execute();
                    }
                    
                    // Insert contact details
                    $email = $bookingData['contact_email'];
                    $phone = $bookingData['contact_phone'];
                    $address = $bookingData['contact_address'];
                    $city = $bookingData['contact_city'];
                    $country = $bookingData['contact_country'];
                    $postalCode = $bookingData['contact_postal'];
                    
                    $contactStmt = $conn->prepare("INSERT INTO booking_contact (Booking_ID, Email, Phone_Number, Address, City, Country, Postal_Code) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $contactStmt->bind_param("issssss", $bookingId, $email, $phone, $address, $city, $country, $postalCode);
                    
                    if ($contactStmt->execute()) {
                        // Generate transaction ID
                        $transactionId = "TXN" . time() . rand(100, 999);
                        
                        // Insert into payments table
                        $paymentStmt = $conn->prepare("INSERT INTO payments (Booking_ID, Transaction_ID, Amount, Card_Last_Four, Card_Name, Payment_Date) VALUES (?, ?, ?, ?, ?, NOW())");
                        $cardLastFour = substr($cardNumber, -4);
                        $paymentStmt->bind_param("isdss", $bookingId, $transactionId, $totalAmount, $cardLastFour, $cardName);
                        $paymentStmt->execute();
                        
                        // Update booking with transaction ID and status
                        $updateStmt = $conn->prepare("UPDATE bookings SET Transaction_ID = ?, Payment_Status = 'Completed', Booking_Status = 'Confirmed' WHERE Booking_ID = ?");
                        $updateStmt->bind_param("si", $transactionId, $bookingId);
                        $updateStmt->execute();
                        
                        // Commit the transaction
                        $conn->commit();
                        
                        // Set success flag
                        $paymentSuccess = true;
                        
                        // Save booking ID to session for ticket page
                        $_SESSION['booking_id'] = $bookingId;
                        $_SESSION['transaction_id'] = $transactionId;
                        
                        // Clear booking data from session but keep booking_id for ticket page
                        unset($_SESSION['booking_data']);
                        unset($_SESSION['selected_flight']);
                        unset($_SESSION['flight_type']);
                        if (isset($_SESSION['selected_return_flight'])) {
                            unset($_SESSION['selected_return_flight']);
                        }
                        
                        // FIXED: Use proper PHP header redirect instead of JavaScript
                        header("Location: ticket.php");
                        exit();
                        
                    } else {
                        throw new Exception("Failed to insert contact details");
                    }
                } else {
                    throw new Exception("Failed to create booking");
                }
            } catch (Exception $e) {
                // Roll back the transaction in case of error
                $conn->rollback();
                $errorMessage = "Error processing payment: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyWay Airlines - Payment</title>
    <style>
        /* CSS styles - copied from book.php for consistency */
   :root {
    --primary-color: #0052cc;
    --secondary-color: #f0f5ff;
    --accent-color: #ff6b6b;
    --text-color: #333;
    --light-gray: #f5f5f5;
    --border-color: #e0e0e0;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background-color: #f9f9f9;
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
    padding: 20px 0;
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
    margin-left: 20px;
}

nav ul li a {
    color: white;
    text-decoration: none;
    transition: opacity 0.3s;
}

nav ul li a:hover {
    opacity: 0.8;
}

.booking-form {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    margin: 30px 0;
    padding: 30px;
}

.form-header {
    text-align: center;
    margin-bottom: 30px;
}

.form-header h2 {
    color: var(--primary-color);
    font-size: 28px;
    margin-bottom: 10px;
}

.form-header p {
    color: #666;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.form-section h3 {
    color: var(--primary-color);
    margin-bottom: 15px;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px 15px;
}

.form-group {
    flex: 1;
    min-width: 200px;
    padding: 0 10px;
    margin-bottom: 15px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0,82,204,0.1);
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    text-align: center;
    transition: background-color 0.3s;
}

.btn-block {
    display: block;
    width: 100%;
}

.btn:hover {
    background-color: #0047b3;
}

.btn:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.price-summary {
    margin-top: 30px;
    padding: 20px;
    background-color: var(--secondary-color);
    border-radius: 4px;
}

.price-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.price-total {
    font-size: 18px;
    font-weight: bold;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border-color);
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

footer {
    background-color: #333;
    color: white;
    text-align: center;
    padding: 20px 0;
    margin-top: 50px;
}

.flight-details-summary {
    background-color: var(--secondary-color);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.flight-details-summary h4 {
    color: var(--primary-color);
    margin-bottom: 15px;
}

.flight-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px dashed var(--border-color);
}

.flight-info:last-child {
    border-bottom: none;
}

.flight-route {
    font-weight: bold;
}

.flight-time {
    color: #666;
}

/* Payment specific styles */
.payment-details {
    background-color: rgba(255, 255, 255, 0.95);
    border-radius: 8px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    margin: 30px 0;
    padding: 30px;
    background-image: url('https://www.touristsecrets.com/wp-content/uploads/2023/09/14-amazing-airplane-window-for-2023-1695909754.jpeg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
}

.payment-details::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 8px;
    z-index: 1;
}

.payment-details > * {
    position: relative;
    z-index: 2;
}

.payment-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    background-color: rgba(255, 255, 255, 0.9);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.card-brands {
    display: flex;
    gap: 10px;
}

.card-brand {
    width: 40px;
    height: 25px;
    background-color: var(--light-gray);
    border-radius: 4px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 10px;
    font-weight: bold;
}

.security-note {
    font-size: 12px;
    color: #666;
    margin-top: 10px;
}

.expiry-cvv-row {
    display: flex;
    gap: 20px;
}

.expiry-cvv-row .form-group {
    flex: 1;
}

.loading {
    display: none;
}

.loading.active {
    display: inline-block;
}

/* Loading overlay styles */
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.loading-content {
    background: white;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>Processing Payment...</h3>
            <p>Please wait while we process your payment securely.</p>
        </div>
    </div>

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
        <?php if($errorMessage): ?>
            <div class="alert alert-danger">
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        <?php endif; ?>

        <div class="booking-form">
            <div class="form-header">
                <h2>Payment Details</h2>
                <p>Complete payment to confirm your flight booking</p>
            </div>

            <div class="flight-details-summary">
                <h4>Booking Summary</h4>
                <div class="flight-info">
                    <div class="flight-route">
                        <p><?php echo htmlspecialchars($selectedFlight['origin']); ?> to <?php echo htmlspecialchars($selectedFlight['destination']); ?></p>
                        <p><?php echo htmlspecialchars($selectedFlight['flight_number']); ?> - <?php echo htmlspecialchars($selectedFlight['airline']); ?></p>
                    </div>
                    <div class="flight-time">
                        <p>Departure: <?php echo date('d M Y H:i', strtotime($selectedFlight['departure_date'] . ' ' . $selectedFlight['departure_time'])); ?></p>
                        <p>Arrival: <?php echo date('d M Y H:i', strtotime($selectedFlight['arrival_date'] . ' ' . $selectedFlight['arrival_time'])); ?></p>
                    </div>
                </div>
                
                <?php if($returnFlight): ?>
                <div class="flight-info">
                    <div class="flight-route">
                        <p><?php echo htmlspecialchars($returnFlight['origin']); ?> to <?php echo htmlspecialchars($returnFlight['destination']); ?></p>
                        <p><?php echo htmlspecialchars($returnFlight['flight_number']); ?> - <?php echo htmlspecialchars($returnFlight['airline']); ?></p>
                    </div>
                    <div class="flight-time">
                        <p>Departure: <?php echo date('d M Y H:i', strtotime($returnFlight['departure_date'] . ' ' . $returnFlight['departure_time'])); ?></p>
                        <p>Arrival: <?php echo date('d M Y H:i', strtotime($returnFlight['arrival_date'] . ' ' . $returnFlight['arrival_time'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="price-summary">
                    <div class="price-row">
                        <span>Passengers:</span>
                        <span><?php echo htmlspecialchars($bookingData['passenger_count']); ?></span>
                    </div>
                    <div class="price-row">
                        <span>Base Fare:</span>
                        <span>₹<?php echo number_format($bookingData['base_fare'], 2); ?></span>
                    </div>
                    <div class="price-row">
                        <span>Taxes & Fees:</span>
                        <span>₹<?php echo number_format($bookingData['total_amount'] - $bookingData['base_fare'], 2); ?></span>
                    </div>
                    <div class="price-total">
                        <span>Total Amount:</span>
                        <span>₹<?php echo number_format($bookingData['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>

           <form method="POST" action="ticket.php" id="paymentForm">
                <div class="form-section">
                    <h3>Card Information</h3>
                    <div class="payment-card">
                        <div class="card-header">
                            <h4>Credit/Debit Card</h4>
                            <div class="card-brands">
                                <div class="card-brand">VISA</div>
                                <div class="card-brand">MC</div>
                                <div class="card-brand">AMEX</div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="card_number">Card Number</label>
                            <input type="text" id="card_number" name="card_number" placeholder="1234567890123456" maxlength="16" required>
                        </div>
                        <div class="form-group">
                            <label for="card_name">Cardholder Name</label>
                            <input type="text" id="card_name" name="card_name" placeholder="As shown on card" required>
                        </div>
                        <div class="expiry-cvv-row">
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" maxlength="5" required>
                            </div>
                            <div class="form-group">
                                <label for="cvv">CVV</label>
                                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4" required>
                            </div>
                        </div>
                        <p class="security-note">Your payment information is secure and encrypted.</p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <button type="submit" name="confirm_payment" class="btn btn-block" id="paymentBtn">
                            Confirm Payment - ₹<?php echo number_format($bookingData['total_amount'], 2); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2025 SkyWay Airlines. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Card number formatting
            const cardNumberInput = document.getElementById('card_number');
            cardNumberInput.addEventListener('input', function(e) {
                // Remove any non-digit characters
                let value = this.value.replace(/\D/g, '');
                this.value = value;
            });
            
            // Expiry date formatting
            const expiryDateInput = document.getElementById('expiry_date');
            expiryDateInput.addEventListener('input', function(e) {
                let value = this.value.replace(/\D/g, '');
                
                if (value.length > 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                
                this.value = value;
            });
            
            // CVV validation
            const cvvInput = document.getElementById('cvv');
            cvvInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '');
            });

            // Form submission handling
            const paymentForm = document.getElementById('paymentForm');
            const paymentBtn = document.getElementById('paymentBtn');

            const loadingOverlay = document.getElementById('loadingOverlay');

            paymentForm.addEventListener('submit', function(e) {
                // Basic validation before submit
                const cardNumber = cardNumberInput.value.trim();
                const cardName = document.getElementById('card_name').value.trim();
                const expiryDate = expiryDateInput.value.trim();
                const cvv = cvvInput.value.trim();  

                if (cardNumber.length < 12 || !cardName || !expiryDate.match(/^\d{2}\/\d{2}$/) || cvv.length < 3) {
                    e.preventDefault();
                    alert('Please fill in all required payment fields correctly.');
                    return false;
                }

                // Show loading overlay
                loadingOverlay.style.display = 'flex';
                paymentBtn.disabled = true;
                
                // Allow form to submit
                return true;
            });
        });
    </script>
</body>
</html>