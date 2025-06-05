<?php
session_start();
include("connection.php"); // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['User_ID'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['User_ID'];
$message = "";

// Check if we need to create the cancellations table
$checkTableQuery = "SHOW TABLES LIKE 'cancellations'";
$tableExists = $conn->query($checkTableQuery);

if ($tableExists->num_rows == 0) {
    // Create cancellations table if it doesn't exist
    $createTableQuery = "CREATE TABLE `cancellations` (
        `Cancellation_ID` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `Original_Booking_ID` int UNSIGNED NOT NULL,
        `User_ID` int NOT NULL,
        `Flight_ID` int UNSIGNED NOT NULL,
        `Cancellation_Date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `Refund_Amount` decimal(10,2) DEFAULT NULL,
        `Refund_Status` enum('Pending','Processed','Rejected') DEFAULT 'Pending',
        `Cancellation_Reason` text,
        `Original_Transaction_ID` varchar(50) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
    
    $conn->query($createTableQuery);
}

// Handle the cancellation process
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
    $bookingId = $_POST['booking_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get booking details before deletion for cancellation record
        $bookingQuery = "SELECT * FROM bookings WHERE Booking_ID = ? AND User_ID = ?";
        $stmt = $conn->prepare($bookingQuery);
        $stmt->bind_param('ii', $bookingId, $userId);
        $stmt->execute();
        $bookingResult = $stmt->get_result();
        
        if ($bookingResult->num_rows > 0) {
            $bookingData = $bookingResult->fetch_assoc();
            
            // Create cancellation record
            $insertCancellationQuery = "INSERT INTO cancellations 
                (Original_Booking_ID, User_ID, Flight_ID, Refund_Amount, Original_Transaction_ID, Cancellation_Reason) 
                VALUES (?, ?, ?, ?, ?, ?)";
            
            // Calculate refund (example: 70% of total amount)
            $refundAmount = $bookingData['Total_Amount'] * 0.7;
            $cancellationReason = isset($_POST['cancel_reason']) ? $_POST['cancel_reason'] : "User cancelled booking";
            
            $stmt = $conn->prepare($insertCancellationQuery);
            $stmt->bind_param('iiidss', $bookingId, $userId, $bookingData['Flight_ID'], 
                             $refundAmount, $bookingData['Transaction_ID'], $cancellationReason);
            $stmt->execute();
            
            // Delete from booking_confirmations
            $deleteConfirmationQuery = "DELETE FROM booking_confirmations WHERE Booking_ID = ?";
            $stmt = $conn->prepare($deleteConfirmationQuery);
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            
            // Delete from booking_contact
            $deleteContactQuery = "DELETE FROM booking_contact WHERE Booking_ID = ?";
            $stmt = $conn->prepare($deleteContactQuery);
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            
            // Delete from passenger_details
            $deletePassengerQuery = "DELETE FROM passenger_details WHERE Booking_ID = ?";
            $stmt = $conn->prepare($deletePassengerQuery);
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            
            // Delete from bookings
            $deleteBookingQuery = "DELETE FROM bookings WHERE Booking_ID = ? AND User_ID = ?";
            $stmt = $conn->prepare($deleteBookingQuery);
            $stmt->bind_param('ii', $bookingId, $userId);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $message = "<div class='alert alert-success'>Booking cancelled successfully! Refund of ₹" . number_format($refundAmount, 2) . " has been initiated.</div>";
        } else {
            throw new Exception("Booking not found or unauthorized access.");
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch user's bookings
$bookingsQuery = "
    SELECT b.*, f.flight_number, f.airline, f.origin, f.destination, 
           f.departure_date, f.departure_time, f.arrival_date, f.arrival_time,
           bc.Confirmation_Number, pd.First_Name, pd.Last_Name
    FROM bookings b
    LEFT JOIN flights f ON b.Flight_ID = f.id
    LEFT JOIN booking_confirmations bc ON b.Booking_ID = bc.Booking_ID
    LEFT JOIN passenger_details pd ON b.Booking_ID = pd.Booking_ID
    WHERE b.User_ID = ?
    ORDER BY b.Booking_Date DESC";

$stmt = $conn->prepare($bookingsQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$bookingsResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .booking-card {
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
        }
        .booking-card.confirmed {
            border-left-color: #28a745;
        }
        .booking-card.pending {
            border-left-color: #ffc107;
        }
        .booking-card.cancelled {
            border-left-color: #dc3545;
        }
        .flight-details {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .flight-path {
            flex-grow: 1;
            text-align: center;
        }
        .flight-divider {
            width: 100px;
            height: 2px;
            background-color: #ddd;
            margin: 0 15px;
            position: relative;
        }
        .flight-divider:after {
            content: '✈';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 16px;
            color: #007bff;
        }
        .booking-actions {
            margin-top: 15px;
        }
        .confirmation-modal .modal-body {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Manage Your Bookings</h1>
                <?php echo $message; ?>
                
                <?php if ($bookingsResult->num_rows > 0): ?>
                    <?php while($booking = $bookingsResult->fetch_assoc()): ?>
                        <div class="card booking-card <?php echo strtolower($booking['Booking_Status']); ?> mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    Booking #<?php echo $booking['Booking_ID']; ?>
                                    <?php if (!empty($booking['Confirmation_Number'])): ?>
                                        <span class="badge bg-success ms-2"><?php echo $booking['Confirmation_Number']; ?></span>
                                    <?php endif; ?>
                                </h5>
                                <span class="badge <?php 
                                    echo ($booking['Booking_Status'] == 'Confirmed') ? 'bg-success' : 
                                         (($booking['Booking_Status'] == 'Cancelled') ? 'bg-danger' : 'bg-warning'); 
                                ?>">
                                    <?php echo $booking['Booking_Status']; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="flight-details">
                                    <div class="text-center">
                                        <h6 class="mb-0"><?php echo date('H:i', strtotime($booking['departure_time'])); ?></h6>
                                        <p class="small text-muted mb-0"><?php echo date('d M Y', strtotime($booking['departure_date'])); ?></p>
                                        <p class="mb-0"><?php echo explode(' (', $booking['origin'])[0]; ?></p>
                                    </div>
                                    <div class="flight-divider"></div>
                                    <div class="text-center">
                                        <h6 class="mb-0"><?php echo date('H:i', strtotime($booking['arrival_time'])); ?></h6>
                                        <p class="small text-muted mb-0"><?php echo date('d M Y', strtotime($booking['arrival_date'])); ?></p>
                                        <p class="mb-0"><?php echo explode(' (', $booking['destination'])[0]; ?></p>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <p><strong>Airline:</strong> <?php echo $booking['airline']; ?> (<?php echo $booking['flight_number']; ?>)</p>
                                        <p><strong>Passenger:</strong> <?php echo $booking['First_Name'] . ' ' . $booking['Last_Name']; ?></p>
                                        <p><strong>Booking Date:</strong> <?php echo date('d M Y, H:i', strtotime($booking['Booking_Date'])); ?></p>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <p><strong>Amount Paid:</strong> ₹<?php echo number_format($booking['Total_Amount'], 2); ?></p>
                                        <p><strong>Payment Status:</strong> 
                                            <span class="badge <?php 
                                                echo ($booking['Payment_Status'] == 'Completed') ? 'bg-success' : 
                                                    (($booking['Payment_Status'] == 'Failed') ? 'bg-danger' : 'bg-warning'); 
                                            ?>">
                                                <?php echo $booking['Payment_Status']; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if($booking['Booking_Status'] != 'Cancelled' && $booking['Payment_Status'] == 'Completed'): ?>
                                    <div class="booking-actions">
                                        <button 
                                            class="btn btn-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#cancelModal<?php echo $booking['Booking_ID']; ?>"
                                        >
                                            Cancel Booking
                                        </button>
                                    </div>
                                    
                                    <!-- Cancel Modal -->
                                    <div class="modal fade confirmation-modal" id="cancelModal<?php echo $booking['Booking_ID']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Cancel Booking #<?php echo $booking['Booking_ID']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-warning">
                                                        <p><strong>Cancellation Policy:</strong></p>
                                                        <ul>
                                                            <li>You will receive approximately 70% of the total amount as refund</li>
                                                            <li>Refund will be processed within 7-10 business days</li>
                                                            <li>This action cannot be undone</li>
                                                        </ul>
                                                    </div>
                                                    
                                                    <form method="post" action="">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['Booking_ID']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label for="cancel_reason" class="form-label">Reason for Cancellation (Optional)</label>
                                                            <select name="cancel_reason" id="cancel_reason" class="form-select">
                                                                <option value="Change of plans">Change of plans</option>
                                                                <option value="Found better deal">Found better deal</option>
                                                                <option value="Emergency">Emergency</option>
                                                                <option value="Other">Other</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="d-flex justify-content-end">
                                                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Keep Booking</button>
                                                            <button type="submit" name="cancel_booking" class="btn btn-danger">Confirm Cancellation</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif($booking['Booking_Status'] == 'Cancelled'): ?>
                                    <div class="alert alert-danger mt-3">
                                        This booking has been cancelled.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        You don't have any bookings yet. <a href="book.php" class="alert-link">Book a flight now!</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
