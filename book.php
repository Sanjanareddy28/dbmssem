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

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=book.php");
    exit();
}

// Check if we have flight selection in the session
if (!isset($_SESSION['selected_flight'])) {
    header("Location: flights.php");
    exit();
}

$selectedFlight = $_SESSION['selected_flight'];
$flightType = $_SESSION['flight_type'];
$returnFlight = isset($_SESSION['selected_return_flight']) ? $_SESSION['selected_return_flight'] : null;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_booking'])) {
    // Get form data
    $passengerCount = $_POST['passenger_count'];
    $totalAmount = $_POST['total_amount'];
    
    // Store all form data in session for payment page
    $_SESSION['booking_data'] = $_POST;
    // Add flight details to booking data
    $_SESSION['booking_data']['base_fare'] = $selectedFlight['price'] * $passengerCount;
    if ($returnFlight) {
        $_SESSION['booking_data']['base_fare'] += $returnFlight['price'] * $passengerCount;
    }
    
    // Redirect to payment page
    header("Location: payment.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyWay Airlines - Book Your Flight</title>
  <style>
        /* CSS styles */
       /* CSS styles */
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
    /* Add background image */
    background-image: url('https://images.unsplash.com/photo-1608023136037-626dad6c6188?fm=jpg&q=60&w=3000&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Nnx8YWlycGxhbmUlMjBmbGlnaHR8ZW58MHx8MHx8fDA%3D');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    background-repeat: no-repeat;
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
    background-color: rgba(255, 255, 255, 0.95);
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

.passenger-container {
    margin-top: 20px;
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: var(--light-gray);
}

.passenger-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
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
                    <li><a href="book.php" class="active">Book</a></li>
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
        <?php if(isset($errorMessage)): ?>
            <div class="alert alert-danger">
                <p><?php echo $errorMessage; ?></p>
            </div>
        <?php endif; ?>

        <div class="booking-form">
            <div class="form-header">
                <h2>Complete Your Booking</h2>
                <p>Provide passenger and contact details to confirm your flight booking</p>
            </div>

            <div class="flight-details-summary">
                <h4>Selected Flight Details</h4>
                <div class="flight-info">
                    <div class="flight-route">
                        <p><?php echo $selectedFlight['origin']; ?> to <?php echo $selectedFlight['destination']; ?></p>
                        <p><?php echo $selectedFlight['flight_number']; ?> - <?php echo $selectedFlight['airline']; ?></p>
                    </div>
                    <div class="flight-time">
                        <p>Departure: <?php echo date('d M Y H:i', strtotime($selectedFlight['departure_date'] . ' ' . $selectedFlight['departure_time'])); ?></p>
                        <p>Arrival: <?php echo date('d M Y H:i', strtotime($selectedFlight['arrival_date'] . ' ' . $selectedFlight['arrival_time'])); ?></p>
                    </div>
                    <div class="flight-price">
                        <p>Price: ₹<?php echo number_format($selectedFlight['price']); ?> per person</p>
                    </div>
                </div>
                
                <?php if($returnFlight): ?>
                <div class="flight-info">
                    <div class="flight-route">
                        <p><?php echo $returnFlight['origin']; ?> to <?php echo $returnFlight['destination']; ?></p>
                        <p><?php echo $returnFlight['flight_number']; ?> - <?php echo $returnFlight['airline']; ?></p>
                    </div>
                    <div class="flight-time">
                        <p>Departure: <?php echo date('d M Y H:i', strtotime($returnFlight['departure_date'] . ' ' . $returnFlight['departure_time'])); ?></p>
                        <p>Arrival: <?php echo date('d M Y H:i', strtotime($returnFlight['arrival_date'] . ' ' . $returnFlight['arrival_time'])); ?></p>
                    </div>
                    <div class="flight-price">
                        <p>Price: ₹<?php echo number_format($returnFlight['price']); ?> per person</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="bookingForm">
                <input type="hidden" name="flight_type" value="<?php echo $flightType; ?>">
                
                <div class="form-section">
                    <h3>Passenger Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="passenger_count">Number of Passengers</label>
                            <select id="passenger_count" name="passenger_count" required>
                                <?php for($i = 1; $i <= 9; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div id="passengers_container">
                        <!-- Passenger forms will be generated here by JavaScript -->
                    </div>
                </div>

                <div class="form-section">
                    <h3>Contact Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_email">Email Address</label>
                            <input type="email" id="contact_email" name="contact_email" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_phone">Phone Number</label>
                            <input type="tel" id="contact_phone" name="contact_phone" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_address">Address</label>
                            <input type="text" id="contact_address" name="contact_address" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_city">City</label>
                            <input type="text" id="contact_city" name="contact_city" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_country">Country</label>
                            <input type="text" id="contact_country" name="contact_country" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_postal">Postal Code</label>
                            <input type="text" id="contact_postal" name="contact_postal" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Price Summary</h3>
                    <div class="price-summary">
                        <div class="price-row">
                            <span>Base Fare:</span>
                            <span id="base_fare">₹0.00</span>
                        </div>
                        <div class="price-row">
                            <span>Taxes & Fees:</span>
                            <span id="taxes_fees">₹0.00</span>
                        </div>
                        <div class="price-total">
                            <span>Total:</span>
                            <span id="total_price">₹0.00</span>
                        </div>
                    </div>
                    <input type="hidden" id="total_amount" name="total_amount" value="0">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <button type="submit" name="submit_booking" class="btn btn-block">
                            Continue to Payment
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
            const passengerCountSelect = document.getElementById('passenger_count');
            const passengersContainer = document.getElementById('passengers_container');
            const baseFareElement = document.getElementById('base_fare');
            const taxesFeesElement = document.getElementById('taxes_fees');
            const totalPriceElement = document.getElementById('total_price');
            const totalAmountInput = document.getElementById('total_amount');
            
            // Get flight prices from PHP
            const outboundPrice = <?php echo $selectedFlight['price']; ?>;
            const returnPrice = <?php echo $returnFlight ? $returnFlight['price'] : 0; ?>;
            const taxRate = 0.15; // 15% tax rate
            
            // Generate passenger forms based on passenger count
            passengerCountSelect.addEventListener('change', function() {
                generatePassengerForms();
                calculateTotal();
            });
            
            // Initialize passenger forms and price calculation
            generatePassengerForms();
            calculateTotal();
            
            function generatePassengerForms() {
                const passengerCount = parseInt(passengerCountSelect.value);
                passengersContainer.innerHTML = '';
                
                for (let i = 1; i <= passengerCount; i++) {
                    const passengerDiv = document.createElement('div');
                    passengerDiv.className = 'passenger-container';
                    passengerDiv.innerHTML = `
                        <div class="passenger-header">
                            <h4>Passenger ${i}</h4>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="passenger_${i}_first_name">First Name</label>
                                <input type="text" id="passenger_${i}_first_name" name="passenger_${i}_first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="passenger_${i}_last_name">Last Name</label>
                                <input type="text" id="passenger_${i}_last_name" name="passenger_${i}_last_name" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="passenger_${i}_gender">Gender</label>
                                <select id="passenger_${i}_gender" name="passenger_${i}_gender" required>
                                    <option value="">-- Select --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="passenger_${i}_dob">Date of Birth</label>
                                <input type="date" id="passenger_${i}_dob" name="passenger_${i}_dob" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="passenger_${i}_type">Passenger Type</label>
                                <select id="passenger_${i}_type" name="passenger_${i}_type" required>
                                    <option value="Adult">Adult</option>
                                    <option value="Child">Child (2-12 years)</option>
                                    <option value="Infant">Infant (under 2 years)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="passenger_${i}_passport">Passport Number</label>
                                <input type="text" id="passenger_${i}_passport" name="passenger_${i}_passport" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="passenger_${i}_request">Special Request (Optional)</label>
                                <textarea id="passenger_${i}_request" name="passenger_${i}_request" rows="2"></textarea>
                            </div>
                        </div>
                    `;
                    
                    passengersContainer.appendChild(passengerDiv);
                }
            }
            
            function calculateTotal() {
                const passengerCount = parseInt(passengerCountSelect.value);
                
                // Calculate base fare
                let baseFare = outboundPrice * passengerCount;
                if (returnPrice > 0) {
                    baseFare += returnPrice * passengerCount;
                }
                
                const taxesFees = baseFare * taxRate;
                const totalPrice = baseFare + taxesFees;
                
                // Update price display
                baseFareElement.textContent = '₹' + baseFare.toFixed(2);
                taxesFeesElement.textContent = '₹' + taxesFees.toFixed(2);
                totalPriceElement.textContent = '₹' + totalPrice.toFixed(2);
                totalAmountInput.value = totalPrice.toFixed(2);
            }
        });
    </script>
</body>
</html>