<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Database configuration
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

// Process refund status update
if(isset($_POST['update_refund'])) {
    $cancellation_id = $_POST['cancellation_id'];
    $new_status = $_POST['refund_status'];
    $notes = $_POST['admin_notes'];
    
    $updateQuery = "UPDATE cancellations SET 
                    Refund_Status = ?, 
                    Admin_Notes = ?,
                    Last_Updated = NOW() 
                    WHERE Cancellation_ID = ?";
                    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ssi", $new_status, $notes, $cancellation_id);
    
    if($stmt->execute()) {
        $success_message = "Refund status updated successfully.";
    } else {
        $error_message = "Error updating refund status: " . $conn->error;
    }
    
    $stmt->close();
}

// Process flight update
if(isset($_POST['update_flight'])) {
    $flight_id = $_POST['flight_id'];
    $price = $_POST['price'];
    $seats = $_POST['seats_available'];
    
    $updateQuery = "UPDATE flights SET 
                    price = ?, 
                    seats_available = ? 
                    WHERE id = ?";
                    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("dii", $price, $seats, $flight_id);
    
    if($stmt->execute()) {
        $success_message = "Flight details updated successfully.";
    } else {
        $error_message = "Error updating flight details: " . $conn->error;
    }
    
    $stmt->close();
}

// Get current tab
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Function to get flight statistics
function getFlightStatistics($conn) {
    $stats = [];
    
    // Total flights
    $result = $conn->query("SELECT COUNT(*) as total FROM flights");
    $stats['total_flights'] = $result->fetch_assoc()['total'];
    
    // Total bookings
    $result = $conn->query("SELECT COUNT(*) as total FROM bookings");
    $stats['total_bookings'] = $result->fetch_assoc()['total'];
    
    // Total cancellations
    $result = $conn->query("SELECT COUNT(*) as total FROM cancellations");
    $stats['total_cancellations'] = $result->fetch_assoc()['total'];
    
    // Total revenue
    $result = $conn->query("SELECT SUM(Total_Amount) as total FROM bookings WHERE Payment_Status = 'Completed'");
    $stats['total_revenue'] = $result->fetch_assoc()['total'];
    
    // Total refunds
    $result = $conn->query("SELECT SUM(Refund_Amount) as total FROM cancellations WHERE Refund_Status = 'Processed'");
    $stats['total_refunds'] = $result->fetch_assoc()['total'];
    
    // Busiest routes
    $result = $conn->query("
        SELECT f.origin, f.destination, COUNT(*) as booking_count
        FROM bookings b
        JOIN flights f ON b.Flight_ID = f.id
        GROUP BY f.origin, f.destination
        ORDER BY booking_count DESC
        LIMIT 5
    ");
    
    $stats['busiest_routes'] = [];
    while($row = $result->fetch_assoc()) {
        $stats['busiest_routes'][] = $row;
    }
    
    return $stats;
}

// Function to get cancellations that need review
function getPendingCancellations($conn) {
    $query = "
        SELECT c.*, f.flight_number, f.airline, f.origin, f.destination, 
               f.departure_date, f.departure_time, u.Email as user_email
        FROM cancellations c
        JOIN flights f ON c.Flight_ID = f.id
        JOIN users u ON c.User_ID = u.User_ID
        WHERE c.Refund_Status = 'Pending'
        ORDER BY c.Cancellation_Date ASC
    ";
    
    $result = $conn->query($query);
    $cancellations = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $cancellations[] = $row;
        }
    }
    
    return $cancellations;
}

// Function to get all cancellations
function getAllCancellations($conn) {
    $query = "
        SELECT c.*, f.flight_number, f.airline, f.origin, f.destination, 
               f.departure_date, f.departure_time, u.Email as user_email
        FROM cancellations c
        JOIN flights f ON c.Flight_ID = f.id
        JOIN users u ON c.User_ID = u.User_ID
        ORDER BY c.Cancellation_Date DESC
    ";
    
    $result = $conn->query($query);
    $cancellations = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $cancellations[] = $row;
        }
    }
    
    return $cancellations;
}

// Function to get all flights
function getAllFlights($conn) {
    $query = "
        SELECT f.*, 
               (SELECT COUNT(*) FROM bookings WHERE Flight_ID = f.id AND Booking_Status = 'Confirmed') as booked_count
        FROM flights f
        ORDER BY f.departure_date, f.departure_time
    ";
    
    $result = $conn->query($query);
    $flights = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $flights[] = $row;
        }
    }
    
    return $flights;
}

// Function to get all bookings
function getAllBookings($conn) {
    $query = "
        SELECT b.*, f.flight_number, f.airline, f.origin, f.destination, 
               f.departure_date, f.departure_time, u.Email as user_email
        FROM bookings b
        JOIN flights f ON b.Flight_ID = f.id
        JOIN users u ON b.User_ID = u.User_ID
        ORDER BY b.Booking_Date DESC
    ";
    
    $result = $conn->query($query);
    $bookings = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }
    
    return $bookings;
}

// Get specific cancellation details if ID is provided
$cancellation_details = null;
if(isset($_GET['cancellation_id'])) {
    $cancellation_id = $_GET['cancellation_id'];
    
    $query = "
        SELECT c.*, f.flight_number, f.airline, f.origin, f.destination, 
               f.departure_date, f.departure_time, f.arrival_date, f.arrival_time,
               u.Email as user_email, u.First_Name, u.Last_Name, u.Phone_Number
        FROM cancellations c
        JOIN flights f ON c.Flight_ID = f.id
        JOIN users u ON c.User_ID = u.User_ID
        WHERE c.Cancellation_ID = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cancellation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $cancellation_details = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Get specific flight details if ID is provided
$flight_details = null;
if(isset($_GET['flight_id'])) {
    $flight_id = $_GET['flight_id'];
    
    $query = "
        SELECT * FROM flights WHERE id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $flight_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $flight_details = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Fetch data based on current tab
$stats = null;
$pending_cancellations = null;
$all_cancellations = null;
$all_flights = null;
$all_bookings = null;

switch($current_tab) {
    case 'dashboard':
        $stats = getFlightStatistics($conn);
        $pending_cancellations = getPendingCancellations($conn);
        break;
    case 'cancellations':
        $all_cancellations = getAllCancellations($conn);
        break;
    case 'flights':
        $all_flights = getAllFlights($conn);
        break;
    case 'bookings':
        $all_bookings = getAllBookings($conn);
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkyWay Airlines - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #ffdd00;
            --secondary-color: #333;
            --accent-color: #0071c2;
            --admin-accent: #d9534f;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
        }
        
        .sidebar {
            background-color: var(--secondary-color);
            color: white;
            min-height: 100vh;
            padding-top: 20px;
        }
        
        .sidebar .nav-link {
            color: #f8f9fa;
            margin-bottom: 5px;
            border-radius: 0;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link.active {
            background-color: var(--admin-accent);
            color: white;
        }
        
        .sidebar .logo {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.4rem;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .main-content {
            padding: 20px;
        }
        
        .header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .stats-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stats-card .icon {
            width: 50px;
            height: 50px;
            background-color: rgba(0, 113, 194, 0.1);
            color: var(--accent-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .panel-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table th {
            background-color: #f8f9fa;
        }
        
        .badge.bg-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge.bg-processed {
            background-color: #198754;
        }
        
        .badge.bg-rejected {
            background-color: #dc3545;
        }
        
        .flight-info {
            font-size: 0.9rem;
        }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .detail-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .flight-route {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        
        .flight-city {
            text-align: center;
        }
        
        .flight-arrow {
            flex-grow: 1;
            height: 2px;
            background-color: #dee2e6;
            margin: 0 15px;
            position: relative;
        }
        
        .flight-arrow:after {
            content: '✈';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            color: var(--admin-accent);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="logo d-flex align-items-center">
                    <i class="bi bi-airplane me-2"></i> SkyWay Admin
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_tab == 'dashboard' ? 'active' : ''; ?>" href="admin.php?tab=dashboard">
                            <i class="bi bi-speedometer2 me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_tab == 'cancellations' ? 'active' : ''; ?>" href="admin.php?tab=cancellations">
                            <i class="bi bi-x-circle me-2"></i> Cancellations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_tab == 'flights' ? 'active' : ''; ?>" href="admin.php?tab=flights">
                            <i class="bi bi-airplane me-2"></i> Flights
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_tab == 'bookings' ? 'active' : ''; ?>" href="admin.php?tab=bookings">
                            <i class="bi bi-calendar-check me-2"></i> Bookings
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($cancellation_details): ?>
                    <!-- Cancellation Detail View -->
                    <div class="header d-flex justify-content-between align-items-center">
                        <h2>Cancellation Details #<?php echo $cancellation_details['Cancellation_ID']; ?></h2>
                        <a href="admin.php?tab=cancellations" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Cancellations
                        </a>
                    </div>
                    
                    <div class="panel-card">
                        <div class="detail-header d-flex justify-content-between">
                            <div>
                                <h4><?php echo $cancellation_details['airline']; ?> (<?php echo $cancellation_details['flight_number']; ?>)</h4>
                                <p class="mb-0 text-muted">Original Booking ID: #<?php echo $cancellation_details['Original_Booking_ID']; ?></p>
                            </div>
                            <div>
                                <span class="badge <?php 
                                    echo ($cancellation_details['Refund_Status'] == 'Processed') ? 'bg-processed' : 
                                        (($cancellation_details['Refund_Status'] == 'Rejected') ? 'bg-rejected' : 'bg-pending'); 
                                ?>">
                                    <?php echo $cancellation_details['Refund_Status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Flight Information</h5>
                                <div class="flight-route">
                                    <div class="flight-city">
                                        <div class="fw-bold"><?php echo date('H:i', strtotime($cancellation_details['departure_time'])); ?></div>
                                        <div class="text-muted small"><?php echo date('d M Y', strtotime($cancellation_details['departure_date'])); ?></div>
                                        <div><?php echo explode(' (', $cancellation_details['origin'])[0]; ?></div>
                                    </div>
                                    <div class="flight-arrow"></div>
                                    <div class="flight-city">
                                        <div class="fw-bold"><?php echo date('H:i', strtotime($cancellation_details['arrival_time'])); ?></div>
                                        <div class="text-muted small"><?php echo date('d M Y', strtotime($cancellation_details['arrival_date'])); ?></div>
                                        <div><?php echo explode(' (', $cancellation_details['destination'])[0]; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Customer Information</h5>
                                <p><strong>Name:</strong> <?php echo $cancellation_details['First_Name'] . ' ' . $cancellation_details['Last_Name']; ?></p>
                                <p><strong>Email:</strong> <?php echo $cancellation_details['user_email']; ?></p>
                                <p><strong>Phone:</strong> <?php echo $cancellation_details['Phone_Number']; ?></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Cancellation Details</h5>
                                <p><strong>Cancellation Date:</strong> <?php echo date('d M Y, H:i', strtotime($cancellation_details['Cancellation_Date'])); ?></p>
                                <p><strong>Reason:</strong> <?php echo $cancellation_details['Cancellation_Reason']; ?></p>
                                <p><strong>Refund Amount:</strong> ₹<?php echo number_format($cancellation_details['Refund_Amount'], 2); ?></p>
                                <p><strong>Transaction ID:</strong> <?php echo $cancellation_details['Original_Transaction_ID']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Update Refund Status</h5>
                                <form method="post" action="">
                                    <input type="hidden" name="cancellation_id" value="<?php echo $cancellation_details['Cancellation_ID']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="refund_status" class="form-label">Refund Status</label>
                                        <select name="refund_status" id="refund_status" class="form-select">
                                            <option value="Pending" <?php echo ($cancellation_details['Refund_Status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Processing" <?php echo ($cancellation_details['Refund_Status'] == 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                            <option value="Processed" <?php echo ($cancellation_details['Refund_Status'] == 'Processed') ? 'selected' : ''; ?>>Processed</option>
                                            <option value="Rejected" <?php echo ($cancellation_details['Refund_Status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="admin_notes" class="form-label">Admin Notes</label>
                                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3"><?php echo $cancellation_details['Admin_Notes']; ?></textarea>
                                    </div>
                                    
                                    <button type="submit" name="update_refund" class="btn btn-primary">Update Refund Status</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif($flight_details): ?>
                    <!-- Flight Detail View -->
                    <div class="header d-flex justify-content-between align-items-center">
                        <h2>Flight Details #<?php echo $flight_details['id']; ?></h2>
                        <a href="admin.php?tab=flights" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Flights
                        </a>
                    </div>
                    
                    <div class="panel-card">
                        <div class="detail-header">
                            <h4><?php echo $flight_details['airline']; ?> (<?php echo $flight_details['flight_number']; ?>)</h4>
                            <p class="mb-0 text-muted">
                                <?php echo explode(' (', $flight_details['origin'])[0]; ?> → 
                                <?php echo explode(' (', $flight_details['destination'])[0]; ?>
                            </p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Flight Schedule</h5>
                                <div class="flight-route">
                                    <div class="flight-city">
                                        <div class="fw-bold"><?php echo date('H:i', strtotime($flight_details['departure_time'])); ?></div>
                                        <div class="text-muted small"><?php echo date('d M Y', strtotime($flight_details['departure_date'])); ?></div>
                                        <div><?php echo explode(' (', $flight_details['origin'])[0]; ?></div>
                                    </div>
                                    <div class="flight-arrow"></div>
                                    <div class="flight-city">
                                        <div class="fw-bold"><?php echo date('H:i', strtotime($flight_details['arrival_time'])); ?></div>
                                        <div class="text-muted small"><?php echo date('d M Y', strtotime($flight_details['arrival_date'])); ?></div>
                                        <div><?php echo explode(' (', $flight_details['destination'])[0]; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Update Flight Information</h5>
                                <form method="post" action="">
                                    <input type="hidden" name="flight_id" value="<?php echo $flight_details['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price (₹)</label>
                                        <input type="number" name="price" id="price" class="form-control" value="<?php echo $flight_details['price']; ?>" min="0" step="0.01">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="seats_available" class="form-label">Seats Available</label>
                                        <input type="number" name="seats_available" id="seats_available" class="form-control" value="<?php echo $flight_details['seats_available']; ?>" min="0">
                                    </div>
                                    
                                    <button type="submit" name="update_flight" class="btn btn-primary">Update Flight</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif($current_tab == 'dashboard'): ?>
                    <!-- Dashboard View -->
                    <div class="header">
                        <h2>Admin Dashboard</h2>
                        <p>Welcome to the SkyWay Airlines Admin Panel. View and manage all airline operations.</p>
                    </div>
                    
                    <!-- Stats Overview -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Total Flights</h6>
                                        <h3><?php echo $stats['total_flights']; ?></h3>
                                    </div>
                                    <div class="icon">
                                        <i class="bi bi-airplane"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Total Bookings</h6>
                                        <h3><?php echo $stats['total_bookings']; ?></h3>
                                    </div>
                                    <div class="icon">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Cancellations</h6>
                                        <h3><?php echo $stats['total_cancellations']; ?></h3>
                                    </div>
                                    <div class="icon">
                                        <i class="bi bi-x-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted">Net Revenue</h6>
                                        <h3>₹<?php echo number_format($stats['total_revenue'] - $stats['total_refunds'], 2); ?></h3>
                                    </div>
                                    <div class="icon">
                                        <i class="bi bi-cash"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Busiest Routes -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="panel-card">
                                <h5 class="card-title">Busiest Routes</h5>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Route</th>
                                            <th>Bookings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($stats['busiest_routes'] as $route): ?>
                                            <tr>
                                                <td>
                                                    <?php echo explode(' (', $route['origin'])[0]; ?> → 
                                                    <?php echo explode(' (', $route['destination'])[0]; ?>
                                                </td>
                                                <td><?php echo $route['booking_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>