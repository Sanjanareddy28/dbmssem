<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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

// Function to get user's bookings
function getUserBookings($conn, $user_id) {
    $sql = "SELECT b.Booking_ID, b.Flight_ID, b.Booking_Date, b.Flight_Type, 
                   b.Passenger_Count, b.Total_Amount, b.Payment_Status, b.Booking_Status,
                   f.flight_number, f.airline, f.origin, f.destination, 
                   f.departure_date, f.departure_time, f.arrival_date, f.arrival_time,
                   bc.Confirmation_Number, bc.Check_In_Status
            FROM bookings b
            JOIN flights f ON b.Flight_ID = f.id
            LEFT JOIN booking_confirmations bc ON b.Booking_ID = bc.Booking_ID
            WHERE b.User_ID = ?
            ORDER BY b.Booking_Date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    return $bookings;
}

// Get user's bookings
$bookings = getUserBookings($conn, $user_id);

// Get user details
$stmt = $conn->prepare("SELECT Full_Name, Email FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Close the connection
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7fa;
            color: #333;
        }
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: #2a52be;
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        h1 {
            margin: 0;
            font-size: 24px;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .user-details {
            font-size: 14px;
        }
        .logout-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 15px;
            font-size: 14px;
        }
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: #2a52be;
        }
        .card h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }
        .card p {
            margin: 0;
            color: #666;
            font-size: 24px;
            font-weight: bold;
        }
        .main-content {
            margin-top: 30px;
        }
        .section-title {
            margin-bottom: 20px;
            font-size: 20px;
            border-bottom: 2px solid #2a52be;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
        }
        tbody tr:hover {
            background-color: #f5f5f5;
        }
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        .status-confirmed {
            background-color: #e6f7ee;
            color: #00a65a;
        }
        .status-pending {
            background-color: #fff8e6;
            color: #f39c12;
        }
        .status-cancelled {
            background-color: #feecec;
            color: #e74c3c;
        }
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-block;
            border: none;
        }
        .btn-primary {
            background-color: #2a52be;
            color: white;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        .btn-success {
            background-color: #00a65a;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #999;
        }
        .empty-state i {
            font-size: 60px;
            margin-bottom: 15px;
            color: #ddd;
        }
        .empty-state p {
            font-size: 16px;
        }
        .nav-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            margin-right: 5px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            background-color: #f8f9fa;
        }
        .tab.active {
            background-color: white;
            border-color: #ddd;
            border-bottom-color: white;
            margin-bottom: -1px;
            font-weight: bold;
            color: #2a52be;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .confirmation-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .detail-value {
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }
            table {
                font-size: 14px;
            }
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            .user-info {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <h1>Flight Booking Dashboard</h1>
                <div class="user-info">
                    <i class="fas fa-user-circle" style="font-size: 30px; margin-right: 10px;"></i>
                    <div class="user-details">
                        <div><?php echo htmlspecialchars($user['Full_Name']); ?></div>
                        <div style="font-size: 12px; opacity: 0.8;"><?php echo htmlspecialchars($user['Email']); ?></div>
                    </div>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <h3>Total Bookings</h3>
                <p><?php echo count($bookings); ?></p>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Confirmed Bookings</h3>
                <p>
                    <?php 
                        $confirmed = array_filter($bookings, function($booking) {
                            return $booking['Booking_Status'] == 'Confirmed';
                        });
                        echo count($confirmed);
                    ?>
                </p>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3>Cancelled Bookings</h3>
                <p>
                    <?php 
                        $cancelled = array_filter($bookings, function($booking) {
                            return $booking['Booking_Status'] == 'Cancelled';
                        });
                        echo count($cancelled);
                    ?>
                </p>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>Upcoming Flights</h3>
                <p>
                    <?php 
                        $today = date('Y-m-d');
                        $upcoming = array_filter($bookings, function($booking) use ($today) {
                            return $booking['departure_date'] >= $today && $booking['Booking_Status'] == 'Confirmed';
                        });
                        echo count($upcoming);
                    ?>
                </p>
            </div>
        </div>

        <div class="main-content">
            <div class="nav-tabs">
                <div class="tab active" onclick="openTab('bookings')">My Bookings</div>
                <div class="tab" onclick="openTab('tickets')">View Tickets</div>
                <div class="tab" onclick="openTab('cancelled')">Cancelled Bookings</div>
            </div>

            <!-- My Bookings Tab -->
            <div id="bookings" class="tab-content active">
                <h2 class="section-title">My Booking History</h2>
                
                <?php if (count($bookings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Flight</th>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php foreach ($bookings as $booking): ?>
    <tr>
        <td>#<?php echo $booking['Booking_ID']; ?></td>
        <td>
            <?php echo htmlspecialchars($booking['airline']); ?><br>
            <small><?php echo htmlspecialchars($booking['flight_number']); ?></small>
        </td>
        <td>
            <?php echo htmlspecialchars($booking['origin']); ?> → 
            <?php echo htmlspecialchars($booking['destination']); ?>
        </td>
        <td>
            <?php 
                $departure = new DateTime($booking['departure_date'] . ' ' . $booking['departure_time']);
                echo $departure->format('M d, Y - H:i'); 
            ?>
        </td>
        <td>
            <?php if ($booking['Booking_Status'] == 'Confirmed'): ?>
                <span class="status status-confirmed">Confirmed</span>
            <?php elseif ($booking['Booking_Status'] == 'Cancelled'): ?>
                <span class="status status-cancelled">Cancelled</span>
            <?php else: ?>
                <span class="status status-pending">Pending</span>
            <?php endif; ?>
        </td>
        <td>₹<?php echo number_format($booking['Total_Amount'], 2); ?></td>
        <td>
            <?php if ($booking['Booking_Status'] == 'Confirmed'): ?>
                <a href="view_ticket.php?id=<?php echo $booking['Booking_ID']; ?>" class="btn btn-primary">View</a>
                <?php if (strtotime($booking['departure_date']) > time()): ?>
                    <!-- Fixed cancel URL parameter -->
                    <a href="cancel.php?booking_id=<?php echo $booking['Booking_ID']; ?>" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            <?php elseif ($booking['Booking_Status'] == 'Pending'): ?>
                <a href="#" class="btn btn-success">Pay Now</a>
            <?php else: ?>
                <span class="status">Cancelled</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <p>You don't have any bookings yet.</p>
                    <a href="index.php" class="btn btn-primary">Book a Flight</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- View Tickets Tab -->
            <div id="tickets" class="tab-content">
                <h2 class="section-title">Your Confirmed Tickets</h2>
                
                <?php if (count($confirmed) > 0): ?>
                    <?php foreach ($confirmed as $ticket): ?>
                        <?php if (!empty($ticket['Confirmation_Number'])): ?>
                        <div class="card" style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3><?php echo htmlspecialchars($ticket['airline']); ?> - <?php echo htmlspecialchars($ticket['flight_number']); ?></h3>
                                <span class="status status-confirmed">Confirmed</span>
                            </div>
                            
                            <div class="confirmation-details">
                                <div class="detail-item">
                                    <div class="detail-label">Confirmation #</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($ticket['Confirmation_Number']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">From</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($ticket['origin']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">To</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($ticket['destination']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Departure</div>
                                    <div class="detail-value">
                                        <?php 
                                            $departure = new DateTime($ticket['departure_date'] . ' ' . $ticket['departure_time']);
                                            echo $departure->format('M d, Y - H:i'); 
                                        ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Arrival</div>
                                    <div class="detail-value">
                                        <?php 
                                            $arrival = new DateTime($ticket['arrival_date'] . ' ' . $ticket['arrival_time']);
                                            echo $arrival->format('M d, Y - H:i'); 
                                        ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Check-in Status</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($ticket['Check_In_Status']); ?></div>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; margin-top: 15px;">
    <a href="view_ticket.php?id=<?php echo $ticket['Booking_ID']; ?>" class="btn btn-primary">View E-Ticket</a>
    <?php if (strtotime($ticket['departure_date']) > time()): ?>
        <!-- Fixed cancel URL parameter -->
        <a href="cancel.php?booking_id=<?php echo $ticket['Booking_ID']; ?>" class="btn btn-danger">Cancel Ticket</a>
    <?php endif; ?>
</div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-plane"></i>
                    <p>You don't have any confirmed tickets.</p>
                    <a href="index.php" class="btn btn-primary">Book a Flight</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Cancelled Bookings Tab -->
            <div id="cancelled" class="tab-content">
                <h2 class="section-title">Cancelled Bookings</h2>
                
                <?php if (count($cancelled) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Flight</th>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>Amount</th>
                            <th>Cancellation Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cancelled as $cancel): ?>
                        <tr>
                            <td>#<?php echo $cancel['Booking_ID']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($cancel['airline']); ?><br>
                                <small><?php echo htmlspecialchars($cancel['flight_number']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($cancel['origin']); ?> → 
                                <?php echo htmlspecialchars($cancel['destination']); ?>
                            </td>
                            <td>
                                <?php 
                                    $departure = new DateTime($cancel['departure_date'] . ' ' . $cancel['departure_time']);
                                    echo $departure->format('M d, Y - H:i'); 
                                ?>
                            </td>
                            <td>₹<?php echo number_format($cancel['Total_Amount'], 2); ?></td>
                            <td>
                                <?php 
                                    // This would need to be fetched from a cancellations table in a real system
                                    // For now, just displaying the booking date as a placeholder
                                    $bookingDate = new DateTime($cancel['Booking_Date']);
                                    echo $bookingDate->format('M d, Y'); 
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>You don't have any cancelled bookings.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // Hide all tab content
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName("tab");
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            
            // Show the specific tab content
            document.getElementById(tabName).classList.add("active");
            
            // Add active class to the button that opened the tab
            document.querySelector(`.tab[onclick="openTab('${tabName}')"]`).classList.add("active");
        }
    </script>
</body>
</html>