<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Student Registration</title>
      <link rel="icon" type="icon/png" sizes="32x32" href="./asserts/images/Sona Logo.png" />
      <link rel="stylesheet" href="styles.css" />
      <style>
         .error { color: red; margin: 5px 0; }
         .success { color: green; font-weight: bold; margin: 5px 0; }
      </style>
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
    $dob = $_POST["dob"];
    $username = trim($_POST["username"]);
    $regno = trim($_POST["regno"]);
    $year_of_join = $_POST["batch"];
    $degree = $_POST["degree"];
    $department = $_POST["department"];
    $personal_email = trim($_POST["personal_email"]);
    $password = $_POST["password"];
    $re_password = $_POST["re-password"];

    // Validation
    if (empty($name) || empty($dob) || empty($username) || empty($regno) || empty($year_of_join) ||
        empty($degree) || empty($department) || empty($personal_email) || empty($password) || empty($re_password)) {
        $error_messages[] = "Please fill all required fields.";
    }
    if ($password !== $re_password) {
        $error_messages[] = "Passwords do not match.";
    }
    if (!filter_var($personal_email, FILTER_VALIDATE_EMAIL)) {
        $error_messages[] = "Invalid email format.";
    }
    if (strlen($password) < 6) {
        $error_messages[] = "Password must be at least 6 characters long.";
    }
    // Stronger password policy
    if (!preg_match("/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&]).{6,}$/", $password)) {
        $error_messages[] = "Password must include uppercase, lowercase, number, and special character.";
    }

    // Check username & email uniqueness
    if (empty($error_messages)) {
        $check_query = "SELECT id FROM student_register WHERE username=? OR personal_email=?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ss", $username, $personal_email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error_messages[] = "Username or email already exists.";
        }
        $stmt->close();
    }

    // Insert if no errors
    if (empty($error_messages)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO student_register 
                (name, dob, username, regno, year_of_join, degree, department, personal_email, password)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssss",
            $name, $dob, $username, $regno, $year_of_join, $degree, $department, $personal_email, $hashed_password
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
         <form action="" method="POST" class="registration-form">
            <div class="registration-container">
               <h2 class="form-title">Student Registration</h2>

<?php
  if (!empty($success_message)) {
    echo "<div class='success'>$success_message</div>";
  }
  if (!empty($error_messages)) {
    foreach ($error_messages as $err) {
      echo "<div class='error'>".htmlspecialchars($err)."</div>";
    }
  }
?>

               <div class="parent">
                  <div class="item div2">
                     <label for="student-name">Name:</label>
                     <input type="text" id="student-name" name="name" placeholder="Name" required />
                  </div>
                  <div class="item div3">
                     <label for="dob">Date Of Birth:</label>
                     <input type="date" name="dob" id="dob" required />
                  </div>
                  <div class="item div3">
                     <label for="username">Username:</label>
                     <input type="text" id="username" name="username" placeholder="Username" required />
                  </div>
                  <div class="item div4">
                     <label for="registration-number">Registration Number:</label>
                     <input type="text" id="registration-number" inputmode="numeric" pattern="\d{14}" title="Enter 12-digit reg no" name="regno" placeholder="Registration No" required />
                  </div>
                  <div class="item div5">
                     <label for="year_of_join">Year of Join:</label>
                     <select name="batch" id="year_of_join" required>
                        <option value="" disabled selected>Select The year</option>
                        <option value="2020">2020-24</option>
                        <option value="2021">2021-25</option>
                        <option value="2022">2022-26</option>
                        <option value="2023">2023-27</option>
                        <option value="2024">2024-28</option>
                        <option value="2025">2025-29</option>
                        <option value="2026">2026-30</option>
                     </select>
                  </div>
                  <div class="item div5">
                     <label for="degree">Degree</label>
                     <select name="degree" id="degree" required>
                        <option value="" disabled selected>Select The Degree</option>
                        <option value="b.tech">B.Tech</option>
                        <option value="m.tech">M.Tech</option>
                        <option value="B.E">B.E</option>
                        <option value="M.E">M.E</option>
                        <option value="bba">BBA</option>
                        <option value="mba">MBA</option>
                     </select>
                  </div>
                  <div class="item div5">
                     <label for="department">Department</label>
                     <select name="department" id="department" required>
                        <option value="" disabled selected>Select the Department</option>
                        <option value="IT">IT</option>
                        <option value="CSE">CSE</option>
                        <option value="ECE">ECE</option>
                        <option value="EEE">EEE</option>
                        <option value="MECH">MECH</option>
                        <option value="CIVIL">CIVIL</option>
                        <option value="AIML">AIML</option>
                        <option value="ADS">ADS</option>
                        <option value="FT">FT</option>
                        <option value="EXE">EXE</option>
                        <option value="CSD">CSD</option>
                     </select>
                  </div>
                  <div class="item div7">
                     <label for="email">Personal Email:</label>
                     <input type="email" inputmode="email" id="email" name="personal_email" placeholder="Personal Email" required />
                  </div>
                  <div class="item div8">
                     <label for="password">Password:</label>
                     <input type="password" name="password" id="password" placeholder="Password" minlength="6" required />
                  </div>
                  <div class="item div9">
                     <label for="re-password">Re Enter Password:</label>
                     <input type="password" name="re-password" id="re-password" placeholder="Re Enter Password" minlength="6" required />
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
