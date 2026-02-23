<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}
?>

<h2>Welcome to HR Dashboard</h2>
<p>Name: <?php echo $_SESSION['user_name']; ?></p>
<p>Email: <?php echo $_SESSION['user_email']; ?></p>

<a href="logout.php">Logout</a>
