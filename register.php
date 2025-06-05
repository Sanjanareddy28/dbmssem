<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phoneNumber = $_POST['phone_number'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $userType = $_POST['user_type'] ?? 'customer'; // Default to 'customer' if not specified

    try {
        // Check if user exists
        $check = $conn->prepare("SELECT User_ID FROM user WHERE Email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            echo "<div class='error-message'>Email already exists. Please use a different email or login.</div>";
        } else {
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO user (Full_Name, Email, Phone_Number, Password, User_Type) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$fullName, $email, $phoneNumber, $password, $userType])) {
                header("Location: login.php");
                exit();
            } else {
                echo "<div class='error-message'>Error during registration.</div>";
            }
        }
    } catch (PDOException $e) {
        echo "<div class='error-message'>Database error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register - Indian Railways</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background-image: url('https://static1.simpleflyingimages.com/wordpress/wp-content/uploads/2023/09/press_release_18_04_23_aa49ead763_5cc3d7372d.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .register-box {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 420px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .register-box h2 {
      margin-bottom: 1.5rem;
      text-align: center;
      color: #0044cc;
    }
    input, select {
      width: 100%;
      padding: 0.75rem;
      margin-bottom: 1rem;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      box-sizing: border-box;
      background: rgba(255, 255, 255, 0.9);
    }
    button {
      width: 100%;
      padding: 0.75rem;
      background-color: #0044cc;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: bold;
      cursor: pointer;
    }
    button:hover {
      background-color: #0033a0;
    }
    .switch {
      text-align: center;
      margin-top: 1rem;
    }
    .switch a {
      color: #0044cc;
      text-decoration: none;
    }
    .switch a:hover {
      text-decoration: underline;
    }
    .error-message {
      background-color: #ffebee;
      color: #c62828;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      text-align: center;
    }
</style>
</head>
<body>
  <form action="register.php" method="POST" class="register-box">
    <h2>Register</h2>
    <input type="text" name="full_name" placeholder="Full Name" required />
    <input type="email" name="email" placeholder="Email Address" required />
    <input type="tel" name="phone_number" placeholder="Phone Number" required />
    <input type="password" name="password" placeholder="Password" required />
    <select name="user_type">
      <option value="customer" selected>Customer</option>
      <option value="staff">Staff</option>
      <!-- Admin option can be enabled for specific registrations or added through database -->
    </select>
    <button type="submit">Create Account</button>
    <div class="switch">
      Already have an account? <a href="login.php">Sign in</a>
    </div>
  </form>
</body>
</html>