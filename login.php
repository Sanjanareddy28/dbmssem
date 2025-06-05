<?php
session_start();
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Updated query to match your table structure
    $stmt = $conn->prepare("SELECT User_ID, Full_Name, Password FROM user WHERE Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if (password_verify($password, $user['Password'])) {
            // Set session variables with correct column names
            $_SESSION['user_id'] = $user['User_ID'];
            $_SESSION['username'] = $user['Full_Name'];
            header("Location: flights.php");
            exit();
        } else {
            echo "Incorrect password.";
        }
    } else {
        echo "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign In - Book Flights</title>
  <link href="https://tse4.mm.bing.net/th?id=OIP.8LP36rWzcM3dP_iNtSq3egHaEm&pid=Api&P=0&h=180" rel="stylesheet">
<style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', sans-serif;
    }
    body {
      background: url('https://www.aci-asiapac.aero/f/blog/3858/1200c630/0760e587-44d5-4b6e-a474-2f1c7a620912.jpg') no-repeat center center fixed;
      background-size: cover;
      color: #1d1d1f;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      filter: none;
    }
    .container {
      background: url('https://www.siasat.com/wp-content/uploads/2023/10/Air-India.jpg') center center;
      background-size: cover;
      backdrop-filter: blur(8px);
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 400px;
      border: 1px solid rgba(255, 255, 255, 0.3);
      position: relative;
    }
    
    .container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.4);
      border-radius: 10px;
      z-index: 1;
    }
    
    .container > * {
      position: relative;
      z-index: 2;
    }
    h1 {
      font-size: 2rem;
      margin-bottom: 1.5rem;
      text-align: center;
      color: #0044cc;
    }
    input {
      width: 100%;
      padding: 1rem;
      margin-bottom: 1rem;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
      background: rgba(255, 255, 255, 0.85);
    }
    button {
      background: #00aa88;
      color: white;
      padding: 1rem;
      border-radius: 8px;
      width: 100%;
      font-size: 1.1rem;
      border: none;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    button:hover {
      background: #008a70;
    }
    .back-link {
      display: block;
      text-align: center;
      margin-top: 1rem;
      font-size: 0.9rem;
    }
    .back-link a {
      text-decoration: none;
      color: #0044cc;
      font-weight: 500;
    }
    .back-link a:hover {
      text-decoration: underline;
    }
</style>
</head>
<body>
  <div class="container">
    <h1>Sign In</h1>
    <form id="loginForm" action="login.php" method="POST">
        <input type="email" placeholder="Email" name="email" required>
        <input type="password" placeholder="Password" name="password" required>
        <button type="submit">Sign In</button>
      </form>
      
    <div class="back-link">
      <p>Don't have an account? <a href="register.php" class="register">Register</a></p>
    </div>
  </div>
</body>
</html>