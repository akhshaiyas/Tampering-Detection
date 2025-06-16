<?php
// Update the username, password, and database name as needed
$host = "localhost";
$username = "root"; // default XAMPP username
$password = "";     // default XAMPP password is empty
$database = "admins";

// Create connection
$con = mysqli_connect($host, $username, $password, $database);

// Check connection
if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit();
}
?>
