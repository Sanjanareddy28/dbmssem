<?php
session_start();
include("db_connect.php"); // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {  // Changed from 'User_ID' to 'user_id' to match dashboard.php
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];  // Changed from 'User_ID' to 'user_id'
$message = "";

// Handle the cancellation process
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
    $bookingId = $_POST['booking_id'];
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Get booking details before cancellation for record
        $bookingQuery = "SELECT * FROM bookings WHERE Booking_ID = ? AND User_ID = ?";
        $stmt = $conn->prepare($bookingQuery);
        if (!$stmt) {
            throw new Exception("Error preparing booking query: " . $conn->error);
        }
        
        $stmt->bindParam(1, $bookingId, PDO::PARAM_INT);
        $stmt->bindParam(2, $userId, PDO::PARAM_INT);
        $stmt->execute();
        $bookingResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($bookingResult) > 0) {
            $bookingData = $bookingResult[0];
            
            // Calculate refund (70% of total amount)
            $refundAmount = $bookingData['Total_Amount'] * 0.7;
            $cancellationReason = isset($_POST['cancel_reason']) ? $_POST['cancel_reason'] : "User cancelled booking";
            
            // Create cancellation record
            $insertCancellationQuery = "INSERT INTO cancellations 
                (Original_Booking_ID, User_ID, Flight_ID, Refund_Amount, Original_Transaction_ID, Cancellation_Reason) 
                VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insertCancellationQuery);
            if (!$stmt) {
                throw new Exception("Error preparing cancellation insert query: " . $conn->error);
            }
            
            $stmt->bindParam(1, $bookingId, PDO::PARAM_INT);
            $stmt->bindParam(2, $userId, PDO::PARAM_INT);
            $stmt->bindParam(3, $bookingData['Flight_ID'], PDO::PARAM_INT);
            $stmt->bindParam(4, $refundAmount, PDO::PARAM_STR);
            $stmt->bindParam(5, $bookingData['Transaction_ID'], PDO::PARAM_STR);
            $stmt->bindParam(6, $cancellationReason, PDO::PARAM_STR);
            $stmt->execute();
            
            // Delete record from booking_confirmations if it exists
            $deleteConfirmationQuery = "DELETE FROM booking_confirmations WHERE Booking_ID = ?";
            $stmt = $conn->prepare($deleteConfirmationQuery);
            if (!$stmt) {
                throw new Exception("Error preparing confirmation delete query: " . $conn->error);
            }
            
            $stmt->bindParam(1, $bookingId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Update booking status to Cancelled
            $updateBookingQuery = "UPDATE bookings SET Booking_Status = 'Cancelled', Payment_Status = 'Refunded' WHERE Booking_ID = ? AND User_ID = ?";
            $stmt = $conn->prepare($updateBookingQuery);
            if (!$stmt) {
                throw new Exception("Error preparing booking update query: " . $conn->error);
            }
            
            $stmt->bindParam(1, $bookingId, PDO::PARAM_INT);
            $stmt->bindParam(2, $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Update flight seats available (add the passenger count back)
            $updateFlightQuery = "UPDATE flights SET seats_available = seats_available + ? WHERE id = ?";
            $stmt = $conn->prepare($updateFlightQuery);
            if (!$stmt) {
                throw new Exception("Error preparing flight update query: " . $conn->error);
            }
            
            $stmt->bindParam(1, $bookingData['Passenger_Count'], PDO::PARAM_INT);
            $stmt->bindParam(2, $bookingData['Flight_ID'], PDO::PARAM_INT);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $message = "<div class='alert alert-success'>Booking #$bookingId cancelled successfully! Refund of ₹" . number_format($refundAmount, 2) . " has been initiated and will be processed within 7-10 business days.</div>";
        } else {
            throw new Exception("Booking not found or unauthorized access.");
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch cancellation details if specific booking ID is provided in URL
$cancellationDetails = null;
if (isset($_GET['booking_id'])) {
    $bookingId = $_GET['booking_id'];
    
    // Get booking details
    $bookingQuery = "
        SELECT b.*, f.flight_number, f.airline, f.origin, f.destination, 
               f.departure_date, f.departure_time, f.arrival_date, f.arrival_time,
               pd.First_Name, pd.Last_Name, bc.Email, bc.Phone_Number
        FROM bookings b
        LEFT JOIN flights f ON b.Flight_ID = f.id
        LEFT JOIN passenger_details pd ON b.Booking_ID = pd.Booking_ID
        LEFT JOIN booking_contact bc ON b.Booking_ID = bc.Booking_ID
        WHERE b.Booking_ID = ? AND b.User_ID = ?
        LIMIT 1";
    
    $stmt = $conn->prepare($bookingQuery);
    if ($stmt) {
        $stmt->bindParam(1, $bookingId, PDO::PARAM_INT);
        $stmt->bindParam(2, $userId, PDO::PARAM_INT);
        $stmt->execute();
        $bookingResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($bookingResult) > 0) {
            $cancellationDetails = $bookingResult[0];
            
            // Get confirmation details if available
            $confirmationQuery = "SELECT * FROM booking_confirmations WHERE Booking_ID = ? LIMIT 1";
            $stmt = $conn->prepare($confirmationQuery);
            if ($stmt) {
                $stmt->bindParam(1, $bookingId, PDO::PARAM_INT);
                $stmt->execute();
                $confirmationResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($confirmationResult) > 0) {
                    $confirmationData = $confirmationResult[0];
                    $cancellationDetails['Confirmation_Number'] = $confirmationData['Confirmation_Number'];
                }
            }
        }
    }
}

// Fetch user's cancelled bookings
$cancelledBookings = [];
$cancelledBookingsQuery = "
    SELECT c.*, f.flight_number, f.airline, f.origin, f.destination, 
           f.departure_date, f.departure_time
    FROM cancellations c
    LEFT JOIN flights f ON c.Flight_ID = f.id
    WHERE c.User_ID = ?
    ORDER BY c.Cancellation_Date DESC";

$stmt = $conn->prepare($cancelledBookingsQuery);
if ($stmt) {
    $stmt->bindParam(1, $userId, PDO::PARAM_INT);
    $stmt->execute();
    $cancelledBookingsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .booking-card {
            margin-bottom: 20px;
            border-left: 5px solid #dc3545;
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
            color: #dc3545;
        }
        .cancellation-policy {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .refund-info {
            border: 1px dashed #dc3545;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        .history-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .history-table th, .history-table td {
            padding: 12px 15px;
        }
        .history-table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .btn-back {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <a href="dashboard.php" class="btn btn-secondary btn-back">← Back to Dashboard</a>
        
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4"><?php echo isset($_GET['booking_id']) ? 'Cancel Booking' : 'Cancellation History'; ?></h1>
                <?php echo $message; ?>
                
                <?php if (isset($cancellationDetails)): ?>
                    <!-- Display booking details for cancellation -->
                    <div class="card booking-card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                Booking #<?php echo $cancellationDetails['Booking_ID']; ?>
                                <?php if (!empty($cancellationDetails['Confirmation_Number'])): ?>
                                    <span class="badge bg-success ms-2"><?php echo $cancellationDetails['Confirmation_Number']; ?></span>
                                <?php endif; ?>
                            </h5>
                            <span class="badge <?php 
                                echo ($cancellationDetails['Booking_Status'] == 'Confirmed') ? 'bg-success' : 
                                     (($cancellationDetails['Booking_Status'] == 'Cancelled') ? 'bg-danger' : 'bg-warning'); 
                            ?>">
                                <?php echo $cancellationDetails['Booking_Status']; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="flight-details">
                                <div class="text-center">
                                    <h6 class="mb-0"><?php echo date('H:i', strtotime($cancellationDetails['departure_time'])); ?></h6>
                                    <p class="small text-muted mb-0"><?php echo date('d M Y', strtotime($cancellationDetails['departure_date'])); ?></p>
                                    <p class="mb-0"><?php echo explode(' (', $cancellationDetails['origin'])[0]; ?></p>
                                </div>
                                <div class="flight-divider"></div>
                                <div class="text-center">
                                    <h6 class="mb-0"><?php echo date('H:i', strtotime($cancellationDetails['arrival_time'])); ?></h6>
                                    <p class="small text-muted mb-0"><?php echo date('d M Y', strtotime($cancellationDetails['arrival_date'])); ?></p>
                                    <p class="mb-0"><?php echo explode(' (', $cancellationDetails['destination'])[0]; ?></p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Airline:</strong> <?php echo $cancellationDetails['airline']; ?> (<?php echo $cancellationDetails['flight_number']; ?>)</p>
                                    <p><strong>Passenger:</strong> <?php echo $cancellationDetails['First_Name'] . ' ' . $cancellationDetails['Last_Name']; ?></p>
                                    <p><strong>Booking Date:</strong> <?php echo date('d M Y, H:i', strtotime($cancellationDetails['Booking_Date'])); ?></p>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <p><strong>Amount Paid:</strong> ₹<?php echo number_format($cancellationDetails['Total_Amount'], 2); ?></p>
                                    <p><strong>Payment Status:</strong> 
                                        <span class="badge <?php 
                                            echo ($cancellationDetails['Payment_Status'] == 'Completed') ? 'bg-success' : 
                                                (($cancellationDetails['Payment_Status'] == 'Failed') ? 'bg-danger' : 'bg-warning'); 
                                        ?>">
                                            <?php echo $cancellationDetails['Payment_Status']; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if($cancellationDetails['Booking_Status'] != 'Cancelled' && $cancellationDetails['Payment_Status'] == 'Completed'): ?>
                                <div class="cancellation-policy mt-4">
                                    <h5>Cancellation Policy</h5>
                                    <ul>
                                        <li>You will receive approximately 70% of the total amount as refund (₹<?php echo number_format($cancellationDetails['Total_Amount'] * 0.7, 2); ?>)</li>
                                        <li>Refund will be processed within 7-10 business days</li>
                                        <li>This action cannot be undone</li>
                                    </ul>
                                </div>
                                
                                <form method="post" action="">
                                    <input type="hidden" name="booking_id" value="<?php echo $cancellationDetails['Booking_ID']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="cancel_reason" class="form-label">Reason for Cancellation</label>
                                        <select name="cancel_reason" id="cancel_reason" class="form-select">
                                            <option value="Change of plans">Change of plans</option>
                                            <option value="Found better deal">Found better deal</option>
                                            <option value="Emergency">Emergency</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-4">
                                        <a href="dashboard.php" class="btn btn-secondary me-2">Keep Booking</a>
                                        <button type="submit" name="cancel_booking" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?')">Confirm Cancellation</button>
                                    </div>
                                </form>
                            <?php elseif($cancellationDetails['Booking_Status'] == 'Cancelled'): ?>
                                <div class="alert alert-danger mt-3">
                                    This booking has already been cancelled.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Show cancellation history -->
                    <h3 class="mb-3">Your Cancellation History</h3>
                    
                    <?php if (isset($cancelledBookingsResult) && count($cancelledBookingsResult) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover history-table">
                                <thead>
                                    <tr>
                                        <th>Cancellation ID</th>
                                        <th>Flight Details</th>
                                        <th>Cancellation Date</th>
                                        <th>Refund Amount</th>
                                        <th>Refund Status</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($cancelledBookingsResult as $cancellation): ?>
                                        <tr>
                                            <td>#<?php echo $cancellation['Cancellation_ID']; ?></td>
                                            <td>
                                                <strong><?php echo $cancellation['airline']; ?> (<?php echo $cancellation['flight_number']; ?>)</strong><br>
                                                <?php echo explode(' (', $cancellation['origin'])[0]; ?> → <?php echo explode(' (', $cancellation['destination'])[0]; ?><br>
                                                <small class="text-muted"><?php echo date('d M Y', strtotime($cancellation['departure_date'])); ?></small>
                                            </td>
                                            <td><?php echo date('d M Y, H:i', strtotime($cancellation['Cancellation_Date'])); ?></td>
                                            <td>₹<?php echo number_format($cancellation['Refund_Amount'], 2); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo ($cancellation['Refund_Status'] == 'Processed') ? 'bg-success' : 
                                                        (($cancellation['Refund_Status'] == 'Rejected') ? 'bg-danger' : 'bg-warning'); 
                                                ?>">
                                                    <?php echo $cancellation['Refund_Status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $cancellation['Cancellation_Reason']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="refund-info mt-4">
                            <h6>Refund Information</h6>
                            <p class="mb-0">Refunds typically take 7-10 business days to process. If you haven't received your refund after this period, please contact our customer support.</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            You don't have any cancelled bookings yet.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>