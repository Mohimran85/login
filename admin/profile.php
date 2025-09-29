<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$username = $_SESSION['username'];

// Handle form submission for updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name = trim($_POST["name"]);
    $dob = $_POST["dob"];
    $regno = trim($_POST["regno"]);
    $year_of_join = $_POST["batch"];
    $degree = $_POST["degree"];
    $department = $_POST["department"];
    $personal_email = trim($_POST["personal_email"]);
    $password = $_POST["password"];
    $re_password = $_POST["re-password"];

    // Validation
    if ($password !== $re_password) {
        $message = "<div style='color:red;'>Passwords do not match.</div>";
    } else {
        // Update query - determine if it's student or teacher
        $tables = ['student_register', 'teacher_register'];
        
        foreach ($tables as $table) {
            $column_email = $table === 'student_register' ? 'personal_email' : 'email';
            
            // Check if user exists in this table
            $check_sql = "SELECT id FROM $table WHERE username=?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update the record
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    if ($table === 'student_register') {
                        $update_sql = "UPDATE $table SET name=?, dob=?, regno=?, year_of_join=?, degree=?, department=?, $column_email=?, password=? WHERE username=?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("sssssssss", $name, $dob, $regno, $year_of_join, $degree, $department, $personal_email, $hashed_password, $username);
                    } else {
                        $update_sql = "UPDATE $table SET name=?, year_of_join=?, department=?, $column_email=?, password=? WHERE username=?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("ssssss", $name, $year_of_join, $department, $personal_email, $hashed_password, $username);
                    }
                } else {
                    if ($table === 'student_register') {
                        $update_sql = "UPDATE $table SET name=?, dob=?, regno=?, year_of_join=?, degree=?, department=?, $column_email=? WHERE username=?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("ssssssss", $name, $dob, $regno, $year_of_join, $degree, $department, $personal_email, $username);
                    } else {
                        $update_sql = "UPDATE $table SET name=?, year_of_join=?, department=?, $column_email=? WHERE username=?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("sssss", $name, $year_of_join, $department, $personal_email, $username);
                    }
                }
                
                if ($update_stmt->execute()) {
                    $message = "<div style='color:green;'>Profile updated successfully!</div>";
                } else {
                    $message = "<div style='color:red;'>Error updating profile: " . $update_stmt->error . "</div>";
                }
                $update_stmt->close();
                break;
            }
            $check_stmt->close();
        }
    }
}

// Fetch user data
$user_data = null;
$user_type = "";
$tables = ['student_register', 'teacher_register'];

foreach ($tables as $table) {
    $column_email = $table === 'student_register' ? 'personal_email' : 'email';
    $columns = $table === 'student_register' 
        ? "name, dob, regno, year_of_join, degree, department, $column_email"
        : "name, year_of_join, department, $column_email";
    
    $sql = "SELECT $columns FROM $table WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $user_type = $table === 'student_register' ? 'student' : 'teacher';
        break;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profile</title>
    <link rel="stylesheet" href="./CSS/profile_css.css" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  </head>
  <body>
    <div class="grid-container">
      <div class="header">
        <div class="menu-icon" onclick="openSidebar()">
          <span class="material-symbols-outlined">menu</span>
        </div>
        <div class="header-logo">
          <img
            class="logo"
            src="./asserts/sona_logo.jpg"
            alt="Sona College Logo"
            height="60px"
            width="200"
          />
        </div>
        <div class="header-title">
          <p>Event Management Dashboard</p>
        </div>
        <div class="header-profile">
          <div class="profile-info" onclick="navigateToProfile()">
            <span class="material-symbols-outlined">account_circle</span>
            <div class="profile-details">
              <span class="profile-name"><?php echo htmlspecialchars($user_data['name'] ?? 'User'); ?></span>
              <span class="profile-role"><?php echo ucfirst($user_type); ?></span>
            </div>
          </div>
        </div>
      </div>
      
      <aside id="sidebar">
        <div class="sidebar-title">
          <div class="sidebar-band">
            <h2 style="color: white; padding: 10px">Admin Panel</h2>
            <span class="material-symbols-outlined" onclick="closeSidebar()">close</span>
          </div>
          <ul class="sidebar-list">
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">dashboard</span>
              <a href="index.php">Home</a>
            </li>
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">event</span>
              <a href="add_event.php">Add Events</a>
            </li>
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">people</span>
              <a href="participants.php">Participants</a>
            </li>
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">bar_chart</span>
              <a href="reports.php">Reports</a>
            </li>
            <li class="sidebar-list-item active">
              <span class="material-symbols-outlined">account_circle</span>
              <a href="profile.php">Profile</a>
            </li>
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">logout</span>
              <a href="logout.php">Logout</a>
            </li>
          </ul>
        </div>
      </aside>
      
      <div class="main">
        <div class="main-profile">
          <div class="profile-header">
            <h2>User Profile</h2>
            <button type="button" id="editBtn" onclick="toggleEdit()">Edit Profile</button>
          </div>
          
          <?php echo $message; ?>
          
          <form method="POST" action="" id="profileForm">
            <div class="item div2">
              <label for="name">Name:</label>
              <input
                type="text"
                id="student-name"
                name="name"
                value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>"
                readonly
                required
              />
            </div>
            <?php if ($user_type === 'student'): ?>
            <div class="item div3">
              <label for="date">Date Of Birth:</label>
              <input type="date" name="dob" id="dob" 
                     value="<?php echo htmlspecialchars($user_data['dob'] ?? ''); ?>" 
                     readonly required />
            </div>

            <div class="item div4">
              <label for="regno">Registration Number: <small style="color: #6c757d; font-weight: normal;">(Cannot be changed)</small></label>
              <input
                type="text"
                id="registration-number"
                name="regno"
                value="<?php echo htmlspecialchars($user_data['regno'] ?? ''); ?>"
                readonly
                required
              />
            </div>
            <?php endif; ?>

            <div class="item div3">
              <label for="Username">Username: <small style="color: #6c757d; font-weight: normal;">(Cannot be changed)</small></label>
              <input
                type="text"
                id="username"
                name="username_display"
                value="<?php echo htmlspecialchars($username); ?>"
                readonly
                disabled
              />
            </div>

            <div class="item div5" style="width: 95%">
              <label for="batch">Year of Join:</label>
              <select name="batch" id="year_of_join" disabled required>
                <option value="">Select The year</option>
                <?php
                $years = ['2020', '2021', '2022', '2023', '2024', '2025', '2026'];
                foreach ($years as $year) {
                    $selected = (isset($user_data['year_of_join']) && $user_data['year_of_join'] == $year) ? 'selected' : '';
                    echo "<option value='$year' $selected>$year-" . ($year + 4) . "</option>";
                }
                ?>
              </select>
            </div>

            <?php if ($user_type === 'student'): ?>
            <div class="item div5" style="width: 95%">
              <label for="degree">Degree</label>
              <select name="degree" id="degree" disabled required>
                <option value="">Select The Degree</option>
                <?php
                $degrees = ['b.tech' => 'B.Tech', 'm.tech' => 'M.Tech', 'B.E' => 'B.E', 'M.E' => 'M.E', 'bba' => 'BBA', 'mba' => 'MBA'];
                foreach ($degrees as $value => $label) {
                    $selected = (isset($user_data['degree']) && $user_data['degree'] == $value) ? 'selected' : '';
                    echo "<option value='$value' $selected>$label</option>";
                }
                ?>
              </select>
            </div>
            <?php endif; ?>

            <div class="item div5" style="width: 95%">
              <label for="department">Department</label>
              <select name="department" id="department" disabled required>
                <option value="">Select the Department</option>
                <?php
                $departments = ['IT', 'CSE', 'ECE', 'EEE', 'MECH', 'CIVIL', 'AIML', 'ADS', 'FT', 'EXE', 'CSD'];
                foreach ($departments as $dept) {
                    $selected = (isset($user_data['department']) && $user_data['department'] == $dept) ? 'selected' : '';
                    echo "<option value='$dept' $selected>$dept</option>";
                }
                ?>
              </select>
            </div>

            <div class="item div7">
              <label for="email"><?php echo $user_type === 'student' ? 'Personal Email:' : 'Email:'; ?></label>
              <input
                type="email"
                id="email"
                name="personal_email"
                value="<?php echo htmlspecialchars($user_data['personal_email'] ?? $user_data['email'] ?? ''); ?>"
                readonly
                required
              />
            </div>

            <div class="item div8">
              <label for="password">New Password (leave blank to keep current):</label>
              <input
                type="password"
                name="password"
                id="password"
                placeholder="New password"
                readonly
              />
            </div>

            <div class="item div9">
              <label for="re-password">Confirm New Password:</label>
              <input
                type="password"
                name="re-password"
                id="re-password"
                placeholder="Confirm new password"
                readonly
              />
            </div>
            
            <div class="item div11">
              <input type="submit" name="update_profile" value="Update Profile" id="submitBtn" style="display:none;" />
              <button type="button" id="cancelBtn" onclick="cancelEdit()" style="display:none;">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <script src="./JS/scripts.js"></script>
    <script>
      // Prevent back button to login page
      if (window.history && window.history.pushState) {
        window.history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function () {
          window.history.pushState(null, null, window.location.href);
        });
      }

      // Toggle edit mode
      function toggleEdit() {
        const editBtn = document.getElementById('editBtn');
        const submitBtn = document.getElementById('submitBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const form = document.getElementById('profileForm');
        
        // Get all form inputs
        const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="date"], input[type="tel"], input[type="password"]');
        const selects = form.querySelectorAll('select');
        
        if (editBtn.textContent === 'Edit Profile') {
          // Enable edit mode
          editBtn.textContent = 'Cancel Edit';
          submitBtn.style.display = 'inline-block';
          cancelBtn.style.display = 'inline-block';
          
          // Enable form fields (except username and regno which should stay disabled)
          inputs.forEach(input => {
            if (input.name !== 'username_display' && input.name !== 'regno') {
              input.removeAttribute('readonly');
            }
          });
          
          selects.forEach(select => {
            select.removeAttribute('disabled');
          });
          
        } else {
          // Disable edit mode
          editBtn.textContent = 'Edit Profile';
          submitBtn.style.display = 'none';
          cancelBtn.style.display = 'none';
          
          // Disable form fields
          inputs.forEach(input => {
            input.setAttribute('readonly', true);
          });
          
          selects.forEach(select => {
            select.setAttribute('disabled', true);
          });
        }
      }
      
      function cancelEdit() {
        // Reload the page to reset all values
        window.location.reload();
      }

      // Form validation for password matching
      document.getElementById('profileForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const rePassword = document.getElementById('re-password').value;
        
        if (password && password !== rePassword) {
          e.preventDefault();
          alert('Passwords do not match!');
          return false;
        }
      });

      // Navigation function for header profile
      function navigateToProfile() {
        if (window.location.pathname.includes('profile.php')) {
          window.location.reload();
        } else {
          window.location.href = 'profile.php';
        }
      }
    </script>
  </body>
</html>
