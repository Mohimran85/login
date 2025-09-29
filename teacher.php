<?php
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "event_management_system";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = "";
$error_messages = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $name = trim($_POST["name"]);
    $username = trim($_POST["Username"]);
    $faculty_id = trim($_POST["regno"]);
    $year_of_join = $_POST["batch"];
    $department = $_POST["department"];
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $re_password = $_POST["re-password"];

    // Validation
    if (empty($name) || empty($username) || empty($faculty_id) || empty($year_of_join) ||
        empty($department) || empty($email) || empty($password) || empty($re_password)) {
        $error_messages[] = "Please fill all required fields.";
    }
    if ($password !== $re_password) {
        $error_messages[] = "Passwords do not match.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_messages[] = "Invalid email format.";
    }
    if (strlen($password) < 6) {
        $error_messages[] = "Password must be at least 6 characters long.";
    }

    // Check username, faculty_id & email uniqueness
    if (empty($error_messages)) {
        $check_query = "SELECT id FROM teacher_register WHERE username=? OR faculty_id=? OR email=?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("sss", $username, $faculty_id, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error_messages[] = "Username, Faculty ID, or email already exists.";
        }
        $stmt->close();
    }

    // Insert if no errors
    if (empty($error_messages)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO teacher_register 
                (name, username, faculty_id, year_of_join, department, email, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssss",
            $name, $username, $faculty_id, $year_of_join, $department, $email, $hashed_password
        );
        if ($stmt->execute()) {
            // Set success message and redirect after showing it
            $success_message = "Registration Successful! Redirecting to login page...";
            echo "<script>
                // Show success popup
                alert('Registration Successful!');
                // Wait 2 seconds then redirect
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
        } else {
            $error_messages[] = "Database error: Registration failed.";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Teacher Registration</title>
    <link rel="icon" type="icon/png" sizes="32x32" href="./asserts/images/Sona Logo.png" />
    <link rel="stylesheet" href="styles.css" />
    <script>
        function checkpassword() {
            var pass = document.getElementById('password').value;
            var re_pass = document.getElementById('re-password').value;
            if (pass !== re_pass) {
                alert("Passwords do not match.");
                event.preventDefault(); // Cancel form submission
            }
        }
    </script>
</head>
<body>
<header>
    <div class="container">
        <div class="icon">
            <img src="./asserts/images/Sona Logo.png" alt="Sona Logo" />
        </div>
        <div class="title">
            <h1>Event Management System</h1>
        </div>
    </div>
</header>
<main class="registration-main">
    <form action="" method="POST" class="registration-form" onsubmit="return checkpassword();">
        <div class="registration-container">
            <h2 class="form-title">Faculty Registration</h2>

            <?php
            if (!empty($success_message)) {
                echo "<div style='color:green; font-weight:bold;'>$success_message</div>";
            }
            if (!empty($error_messages)) {
                foreach ($error_messages as $err) {
                    echo "<div style='color:red;'>" . htmlspecialchars($err) . "</div>";
                }
            }
            ?>

            <div class="parent">
                <div class="item div2">
                    <label for="name">Faculty Name:</label>
                    <input type="text" name="name" placeholder="Faculty Name" required />
                </div>
                <div class="item div3">
                    <label for="Username">Username:</label>
                    <input type="text" name="Username" placeholder="Username" required />
                </div>
                <div class="item div4">
                    <label for="regno">Faculty ID:</label>
                    <input type="text" name="regno" inputmode="numeric" pattern="\d*" oninput="this.value = this.value.replace(/\D/g,'');" placeholder="Faculty Id" required />
                </div>
                <div class="item div5">
                    <label for="batch">Year of Join:</label>
                    <input type="date" name="batch" placeholder="Year of Join" required />
                </div>
                <div class="item div6">
                    <label for="department">Department:</label>
                    <select name="department" required>
                        <option value="" disabled selected>Select The Department</option>
                        <option value="IT">IT</option>
                        <option value="CSE">CSE</option>
                        <option value="ECE">ECE</option>
                        <option value="EEE">EEE</option>
                        <option value="MECH">MECH</option>
                        <option value="CIVIL">CIVIL</option>
                    </select>
                </div>
                <div class="item div7">
                    <label for="email">Email:</label>
                    <input type="email" name="email" placeholder="Email" required />
                </div>
                <div class="item div8">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" placeholder="Password" required />
                </div>
                <div class="item div9">
                    <label for="re-password">Re Enter Password:</label>
                    <input type="password" name="re-password" id="re-password" placeholder="Re-enter Password" required />
                </div>
                <div class="item div11">
                    <input type="submit" value="Register" id="button" />
                </div>
            </div>
        </div>
    </form>
</main>
<footer>
    <p>&copy; 2025 Event Management System. All rights reserved.</p>
</footer>
<script src="scripts.js"></script>
</body>
</html>
