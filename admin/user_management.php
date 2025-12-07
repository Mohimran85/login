<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    // Database connection
    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get user data for header profile
    $username  = $_SESSION['username'];
    $user_data = null;
    $user_type = "";
    $is_admin  = false;
    $tables    = ['student_register', 'teacher_register'];

    foreach ($tables as $table) {
        $sql  = "SELECT name FROM $table WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $user_type = $table === 'student_register' ? 'student' : 'teacher';

            // Check if user is admin (you can modify this logic based on your admin identification)
            if ($user_type === 'teacher') {
                // Add your admin check logic here - for now, all teachers have admin access
                $is_admin = true;
            }
            break;
        }
        $stmt->close();
    }

                                 // Check teacher status if user is a teacher
    $teacher_status = 'teacher'; // Default status
    if ($user_type === 'teacher') {
        $teacher_status_sql  = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
        $teacher_status_stmt = $conn->prepare($teacher_status_sql);
        $teacher_status_stmt->bind_param("s", $username);
        $teacher_status_stmt->execute();
        $teacher_status_result = $teacher_status_stmt->get_result();

        if ($teacher_status_result->num_rows > 0) {
            $status_data    = $teacher_status_result->fetch_assoc();
            $teacher_status = $status_data['status'];
        }
        $teacher_status_stmt->close();
    }

    // Redirect teachers without admin access to teacher panel
    if ($user_type === 'teacher' && ! in_array($teacher_status, ['admin', 'teacher'])) {
        $_SESSION['access_denied'] = 'Your account access is restricted. Please contact an administrator for access to management features.';
        header("Location: ../teacher/index.php");
        exit();
    }

    // Only allow admin-level teachers to access user management
    if ($user_type === 'teacher' && $teacher_status !== 'admin') {
        $_SESSION['access_denied'] = 'Only administrators can access user management. Your role is: ' . ucfirst($teacher_status);
        header("Location: ../teacher/index.php");
        exit();
    }

    // Redirect students who shouldn't have access to user management
    if ($user_type === 'student') {
        header("Location: ../student/index.php");
        exit();
    }

    // Function to safely check and add status column
    function ensureStatusColumn($conn, $table_name)
    {
        $check_column  = "SHOW COLUMNS FROM $table_name LIKE 'status'";
        $column_result = $conn->query($check_column);

        if ($column_result->num_rows == 0) {
            // Set default based on table type
            $default_status = ($table_name === 'teacher_register') ? 'teacher' : 'student';
            $add_column     = "ALTER TABLE $table_name ADD COLUMN status VARCHAR(20) DEFAULT '$default_status'";
            $conn->query($add_column);
        } else {
            // Update existing active/inactive values to new role-based system
            $update_active = "UPDATE $table_name SET status = CASE
                              WHEN status = 'active' THEN '" . (($table_name === 'teacher_register') ? 'teacher' : 'student') . "'
                              WHEN status = 'inactive' THEN 'inactive'
                              ELSE status
                              END";
            $conn->query($update_active);
        }
    }

    // Ensure status column exists in both tables
    ensureStatusColumn($conn, 'student_register');
    ensureStatusColumn($conn, 'teacher_register');

    // Handle user actions (delete, status change)
    $success_message = '';
    $error_message   = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'delete_user':
                    $user_id    = (int) $_POST['user_id'];
                    $table_name = $_POST['table_name'];

                    $allowed_tables = ['student_register', 'teacher_register'];
                    if (in_array($table_name, $allowed_tables)) {
                        $delete_sql  = "DELETE FROM $table_name WHERE id = ?";
                        $delete_stmt = $conn->prepare($delete_sql);
                        $delete_stmt->bind_param("i", $user_id);

                        if ($delete_stmt->execute()) {
                            $success_message = "User deleted successfully!";
                        } else {
                            $error_message = "Error deleting user: " . $conn->error;
                        }
                        $delete_stmt->close();
                    }
                    break;

                case 'change_role':
                    $user_id    = (int) $_POST['user_id'];
                    $table_name = $_POST['table_name'];
                    $new_role   = $_POST['new_role'];

                    $allowed_tables = ['student_register', 'teacher_register'];
                    $allowed_roles  = ['admin', 'teacher', 'student'];

                    if (in_array($table_name, $allowed_tables) && in_array($new_role, $allowed_roles)) {
                        // Ensure status column exists
                        ensureStatusColumn($conn, $table_name);

                        // Validate role assignment based on table
                        $valid_assignment = true;
                        if ($table_name === 'student_register' && ! in_array($new_role, ['student'])) {
                            $valid_assignment = false;
                            $error_message    = "Students can only have 'student' role.";
                        }
                        if ($table_name === 'teacher_register' && ! in_array($new_role, ['admin', 'teacher'])) {
                            $valid_assignment = false;
                            $error_message    = "Teachers can only have 'admin' or 'teacher' roles.";
                        }

                        if ($valid_assignment) {
                            $update_sql  = "UPDATE $table_name SET status = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("si", $new_role, $user_id);

                            if ($update_stmt->execute()) {
                                $success_message = "User role updated successfully to " . ucfirst($new_role) . "!";
                            } else {
                                $error_message = "Error updating user role: " . $conn->error;
                            }
                            $update_stmt->close();
                        }
                    }
                    break;

                case 'edit_user':
                    $user_id    = (int) $_POST['user_id'];
                    $table_name = $_POST['table_name'];
                    $name       = trim($_POST['name']);
                    $username   = trim($_POST['username']);
                    $email      = trim($_POST['email']);
                    $department = $_POST['department'];
                    $identifier = trim($_POST['identifier']);

                    $allowed_tables = ['student_register', 'teacher_register'];

                    if (in_array($table_name, $allowed_tables)) {
                        // Validation
                        if (empty($name) || empty($username) || empty($email) || empty($department) || empty($identifier)) {
                            $error_message = "All fields are required!";
                        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $error_message = "Invalid email format!";
                        } else {
                            // Check for username/email uniqueness (excluding current user)
                            $email_column      = ($table_name === 'student_register') ? 'personal_email' : 'email';
                            $identifier_column = ($table_name === 'student_register') ? 'regno' : 'faculty_id';

                            $check_unique_sql = "SELECT id FROM $table_name WHERE (username = ? OR $email_column = ? OR $identifier_column = ?) AND id != ?";
                            $check_stmt       = $conn->prepare($check_unique_sql);
                            $check_stmt->bind_param("sssi", $username, $email, $identifier, $user_id);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();

                            if ($check_result->num_rows > 0) {
                                $error_message = "Username, email, or identifier already exists for another user!";
                            } else {
                                // Update user information
                                $update_sql  = "UPDATE $table_name SET name = ?, username = ?, $email_column = ?, department = ?, $identifier_column = ? WHERE id = ?";
                                $update_stmt = $conn->prepare($update_sql);
                                $update_stmt->bind_param("sssssi", $name, $username, $email, $department, $identifier, $user_id);

                                if ($update_stmt->execute()) {
                                    $success_message = "User information updated successfully!";
                                } else {
                                    $error_message = "Error updating user information: " . $conn->error;
                                }
                                $update_stmt->close();
                            }
                            $check_stmt->close();
                        }
                    }
                    break;
            }
        }

        // Counselor-student assignment not required per current requirement; no-op here.
    }

                                                                                // Get filter parameters
    $filter_user_type = isset($_GET['user_type']) ? $_GET['user_type'] : 'all'; // Default to 'all' for admin users
    $filter_status    = isset($_GET['status']) ? $_GET['status'] : 'all';
    $search_query     = isset($_GET['search']) ? $_GET['search'] : '';
    $entries_param    = isset($_GET['entries']) ? $_GET['entries'] : '10';
    $entries_per_page = ($entries_param === 'all') ? PHP_INT_MAX : (int) $entries_param;
    $current_page     = isset($_GET['page']) ? (int) $_GET['page'] : 1;

    // Restrict teachers to only manage teachers (except admin-level teachers)
    if ($user_type === 'teacher' && $teacher_status !== 'admin') {
        $filter_user_type = 'teacher';
    }

    // Validate search query
    $search_error = '';
    if (! empty($search_query) && strlen(trim($search_query)) < 2) {
        $search_error = 'Search query must be at least 2 characters long.';
        $search_query = '';
    }

    $student_query = "SELECT id, name, username, personal_email as email, regno as identifier, department,
                             COALESCE(year_of_join, DATE(NOW())) as year_of_join,
                             COALESCE(status, 'student') as status, 'student' as user_type,
                             id as sort_order
                      FROM student_register WHERE 1=1";

    $teacher_query = "SELECT id, name, username, email, faculty_id as identifier, department,
                             COALESCE(year_of_join, DATE(NOW())) as year_of_join,
                             COALESCE(status, 'teacher') as status, 'teacher' as user_type,
                             id as sort_order
                      FROM teacher_register WHERE 1=1";

    // Add search conditions to queries
    if (! empty($search_query)) {
        $student_query .= " AND (name LIKE ? OR username LIKE ? OR personal_email LIKE ? OR regno LIKE ?)";
        $teacher_query .= " AND (name LIKE ? OR username LIKE ? OR email LIKE ? OR faculty_id LIKE ?)";
    }

    // Add status filter to queries
    if ($filter_status !== 'all') {
        $student_query .= " AND COALESCE(status, 'student') = ?";
        $teacher_query .= " AND COALESCE(status, 'teacher') = ?";
    }

    // Combine queries based on user type filter and user permissions
    $params      = [];
    $param_types = "";

    if ($user_type === 'teacher' && $teacher_status !== 'admin') {
        // Non-admin teachers can only see teacher records
        $final_query      = $teacher_query . " ORDER BY id DESC";
        $count_base_query = "SELECT COUNT(*) as total FROM teacher_register WHERE 1=1";
    } elseif ($filter_user_type === 'student') {
        $final_query      = $student_query . " ORDER BY id DESC";
        $count_base_query = "SELECT COUNT(*) as total FROM student_register WHERE 1=1";
    } elseif ($filter_user_type === 'teacher') {
        $final_query      = $teacher_query . " ORDER BY id DESC";
        $count_base_query = "SELECT COUNT(*) as total FROM teacher_register WHERE 1=1";
    } else {
        $final_query      = "($student_query) UNION ($teacher_query) ORDER BY sort_order DESC";
        $count_base_query = "SELECT COUNT(*) as total FROM (($student_query) UNION ($teacher_query)) as combined_users";
    }

    // Add search conditions to parameters
    if (! empty($search_query)) {
        $search_param = "%$search_query%";
        if (($user_type === 'teacher' && $teacher_status !== 'admin') || $filter_user_type === 'teacher') {
            // Only teacher search parameters
            for ($i = 0; $i < 4; $i++) {
                $params[] = $search_param;
                $param_types .= "s";
            }
        } elseif ($filter_user_type === 'student') {
            // Only student search parameters
            for ($i = 0; $i < 4; $i++) {
                $params[] = $search_param;
                $param_types .= "s";
            }
        } else {
            // Both student and teacher search parameters
            for ($i = 0; $i < 8; $i++) {
                $params[] = $search_param;
                $param_types .= "s";
            }
        }
    }

    // Add status filter to parameters
    if ($filter_status !== 'all') {
        if (($user_type === 'teacher' && $teacher_status !== 'admin') || $filter_user_type === 'teacher') {
            // Only teacher status parameter
            $params[] = $filter_status;
            $param_types .= "s";
        } elseif ($filter_user_type === 'student') {
            // Only student status parameter
            $params[] = $filter_status;
            $param_types .= "s";
        } else {
            // Both student and teacher status parameters
            $params[] = $filter_status;
            $params[] = $filter_status;
            $param_types .= "ss";
        }
    }

    // Build count query with same conditions
    if (! empty($search_query)) {
        if (($user_type === 'teacher' && $teacher_status !== 'admin') || $filter_user_type === 'teacher') {
            $count_base_query .= " AND (name LIKE ? OR username LIKE ? OR email LIKE ? OR faculty_id LIKE ?)";
        } elseif ($filter_user_type === 'student') {
            $count_base_query .= " AND (name LIKE ? OR username LIKE ? OR personal_email LIKE ? OR regno LIKE ?)";
        } else {
            // For 'all' users, we need to handle UNION differently
            $student_count_query = "SELECT COUNT(*) as total FROM student_register WHERE 1=1 AND (name LIKE ? OR username LIKE ? OR personal_email LIKE ? OR regno LIKE ?)";
            $teacher_count_query = "SELECT COUNT(*) as total FROM teacher_register WHERE 1=1 AND (name LIKE ? OR username LIKE ? OR email LIKE ? OR faculty_id LIKE ?)";

            if ($filter_status !== 'all') {
                $student_count_query .= " AND COALESCE(status, 'student') = ?";
                $teacher_count_query .= " AND COALESCE(status, 'teacher') = ?";
            }

            $count_base_query = "SELECT (($student_count_query) + ($teacher_count_query)) as total";
        }
    }

    if ($filter_status !== 'all' && $filter_user_type !== 'all') {
        $count_base_query .= " AND COALESCE(status, '" . (($filter_user_type === 'student') ? 'student' : 'teacher') . "') = ?";
    } // Get total count for pagination
    if (! empty($params)) {
        $count_stmt = $conn->prepare($count_base_query);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $total_users = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $total_users = $conn->query($count_base_query)->fetch_assoc()['total'];
    }

    // Calculate pagination
    $total_pages = ceil($total_users / $entries_per_page);
    $offset      = ($current_page - 1) * $entries_per_page;

    // Add pagination to final query
    if ($entries_per_page < PHP_INT_MAX) {
        $final_query .= " LIMIT $entries_per_page OFFSET $offset";
    }

    // Execute final query
    if (! empty($params)) {
        $users_stmt = $conn->prepare($final_query);
        if ($users_stmt) {
            $users_stmt->bind_param($param_types, ...$params);
            $users_stmt->execute();
            $users_result = $users_stmt->get_result();
        } else {
            $error_message = "Query preparation failed: " . $conn->error;
            $users_result  = false;
        }
    } else {
        $users_result = $conn->query($final_query);
        if (! $users_result) {
            $error_message = "Query execution failed: " . $conn->error;
        }
    }

    // Get statistics (only for teachers if user is non-admin teacher)
    if ($user_type === 'teacher' && $teacher_status !== 'admin') {
        $total_students  = 0; // Non-admin teachers can't see student count
        $total_teachers  = $conn->query("SELECT COUNT(*) as count FROM teacher_register")->fetch_assoc()['count'];
        $active_students = 0; // Non-admin teachers can't see student count

        // Safe query for active/admin teachers
        $active_teachers_result = $conn->query("SELECT COUNT(*) as count FROM teacher_register WHERE COALESCE(status, 'teacher') IN ('teacher', 'admin')");
        $active_teachers        = $active_teachers_result ? $active_teachers_result->fetch_assoc()['count'] : $total_teachers;
    } else {
        $total_students = $conn->query("SELECT COUNT(*) as count FROM student_register")->fetch_assoc()['count'];
        $total_teachers = $conn->query("SELECT COUNT(*) as count FROM teacher_register")->fetch_assoc()['count'];

        // Safe queries for active users
        $active_students_result = $conn->query("SELECT COUNT(*) as count FROM student_register WHERE COALESCE(status, 'student') = 'student'");
        $active_students        = $active_students_result ? $active_students_result->fetch_assoc()['count'] : $total_students;

        $active_teachers_result = $conn->query("SELECT COUNT(*) as count FROM teacher_register WHERE COALESCE(status, 'teacher') IN ('teacher', 'admin')");
        $active_teachers        = $active_teachers_result ? $active_teachers_result->fetch_assoc()['count'] : $total_teachers;
    }

    // No counselor-student assignment UI; dropdown datasets removed.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
    <link rel="stylesheet" href="./CSS/report.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* User Management Specific Styles */
        .main {
            padding: 20px;
            overflow-y: auto;
            max-height: none;
        }

        .main-content {
            max-width: none;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #0c3878;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card h3 {
            color: #0c3878;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
        }

        .filters-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .filter-group input,
        .filter-group select {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #0c3878;
        }

        .users-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px 25px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0c3878;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }

        .user-details h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .user-details p {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: #666;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-admin {
            background: #e7f3ff;
            color: #0c5460;
            border: 1px solid #b3d9ff;
        }

        .status-teacher {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-student {
            background: #cfe2ff;
            color: #084298;
        }

        .user-type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-student {
            background: #cfe2ff;
            color: #084298;
        }

        .type-teacher {
            background: #d1ecf1;
            color: #0c5460;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #0c3878;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #000;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-success {
            background: #198754;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .add-user-btn {
            background: #0c3878;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .add-user-btn:hover {
            background: #0a2d5f;
            transform: translateY(-2px);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d1e7dd;
            border: 1px solid #a3cfbb;
            color: #0a3622;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f1aeb5;
            color: #721c24;
        }

        .alert-success::before {
            content: "✓";
            font-weight: bold;
        }

        .alert-error::before {
            content: "⚠";
            font-weight: bold;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            padding: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #0c3878;
            color: white;
            border-color: #0c3878;
        }

        .pagination .current {
            background: #0c3878;
            color: white;
            border-color: #0c3878;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .modal h3 {
            color: #333;
            margin-bottom: 15px;
        }

        .modal p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .filters-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .users-table {
                font-size: 12px;
            }

            .users-table th,
            .users-table td {
                padding: 10px 8px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .modal-content {
                margin: 20% auto;
                padding: 20px;
            }
        }

        /* Export dropdown styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .dropdown-content {
            position: absolute;
            background-color: #f9f9f9;
            min-width: 220px;
            width: max-content;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1000;
            right: 0;
            border-radius: 6px;
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #eee;
            white-space: nowrap;
            font-size: 14px;
        }

        .dropdown-content a:last-child {
            border-bottom: none;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
            border-radius: 6px;
        }

        /* Edit Modal Form Styles */
        .modal-content .filter-group {
            margin-bottom: 15px;
        }

        .modal-content .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .modal-content .filter-group input:focus,
        .modal-content .filter-group select:focus {
            outline: none;
            border-color: #0c3878 !important;
            box-shadow: 0 0 0 2px rgba(12, 56, 120, 0.1);
        }

        .modal-content .filter-group input.error,
        .modal-content .filter-group select.error {
            border-color: #dc3545 !important;
            background-color: #fff5f5;
        }

        .modal-content .filter-group small {
            display: block;
            margin-top: 5px;
        }

        /* Form validation styles */
        .form-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
        <div class="header">
            <div class="menu-icon">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">  
                <img class="logo" src="../sona_logo.jpg" alt="Sona College Logo" height="60px" width="200">
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

        <!-- Sidebar -->
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
                        <span class="material-symbols-outlined">people</span>
                        <a href="participants.php">Participants</a>
                    </li>
                    <li class="sidebar-list-item active">
                        <span class="material-symbols-outlined">manage_accounts</span>
                        <a href="user_management.php">User Management</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">bar_chart</span>
                        <a href="reports.php">Reports</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">account_circle</span>
                        <a href="<?php echo $user_type === 'teacher' ? '../teacher/profile.php' : 'profile.php'; ?>">Profile</a>
                    </li>
                    <?php if ($user_type === 'teacher'): ?>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">dashboard</span>
                        <a href="../teacher/index.php">Teacher Dashboard</a>
                    </li>
                    <?php endif; ?>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">logout</span>
                        <a href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main">
            <div class="main-content">
                <!-- Alert Messages -->
                <?php if (! empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (! empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if (! empty($search_error)): ?>
                    <div class="alert alert-error"><?php echo $search_error; ?></div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <?php if ($user_type !== 'teacher' || $teacher_status === 'admin'): ?>
                    <div class="stat-card">
                        <h3>Total Students</h3>
                        <div class="number"><?php echo $total_students; ?></div>
                        <div class="label">Registered Students</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-card">
                        <h3>Total Teachers</h3>
                        <div class="number"><?php echo $total_teachers; ?></div>
                        <div class="label">Registered Teachers</div>
                    </div>
                    <?php if ($user_type !== 'teacher' || $teacher_status === 'admin'): ?>
                    <div class="stat-card">
                        <h3>Active Students</h3>
                        <div class="number"><?php echo $active_students; ?></div>
                        <div class="label">Currently Active</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-card">
                        <h3>Active Teachers</h3>
                        <div class="number"><?php echo $active_teachers; ?></div>
                        <div class="label">Currently Active</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-container">
                    <div class="filters-header">
                        <h3>🔍 Filter                                                                                                                                                                                                                                                                                                                         <?php echo($user_type === 'teacher' && $teacher_status !== 'admin') ? 'Teachers' : 'Users'; ?></h3>

                    </div>

                    <form method="GET" action="">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label for="search">Search                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo($user_type === 'teacher' && $teacher_status !== 'admin') ? 'Teachers' : 'Users'; ?></label>
                                <input type="text" id="search" name="search"
                                       placeholder="Name, Username, Email, ID..."
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                                <small style="color: #666; font-size: 12px;">Minimum 2 characters required</small>
                            </div>

                            <?php if ($user_type !== 'teacher' || $teacher_status === 'admin'): ?>
                            <div class="filter-group">
                                <label for="user_type">User Type</label>
                                <select id="user_type" name="user_type">
                                    <option value="all"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo $filter_user_type === 'all' ? 'selected' : ''; ?>>All Users</option>
                                    <option value="student"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  <?php echo $filter_user_type === 'student' ? 'selected' : ''; ?>>Students</option>
                                    <option value="teacher"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  <?php echo $filter_user_type === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                                </select>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="user_type" value="teacher">
                            <?php endif; ?>

                            <div class="filter-group">
                                <label for="status">Role/Status</label>
                                <select id="status" name="status">
                                    <option value="all"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                    <option value="admin"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo $filter_status === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="teacher"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo $filter_status === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                    <option value="student"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo $filter_status === 'student' ? 'selected' : ''; ?>>Student</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="entries">Show Entries</label>
                                <select id="entries" name="entries">
                                    <option value="10"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo $entries_param === '10' ? 'selected' : ''; ?>>10</option>
                                    <option value="25"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo $entries_param === '25' ? 'selected' : ''; ?>>25</option>
                                    <option value="50"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo $entries_param === '50' ? 'selected' : ''; ?>>50</option>
                                    <option value="all"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo $entries_param === 'all' ? 'selected' : ''; ?>>All</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-symbols-outlined">search</span>
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Class Counselor Management Link -->
                <div class="filters-container">
                    <div class="filters-header">
                        <h3>👨‍🏫 Class Counselors</h3>
                        <a href="./manage_counselors.php" class="add-user-btn" onclick="console.log('Navigating to manage counselors...'); return true;">
                            <span class="material-symbols-outlined">manage_accounts</span>
                            Manage Counselors
                        </a>
                    </div>
                    <p style="margin:0; color:#555;">Use Manage Counselors to assign students by registration number ranges to counselors.</p>
                </div>

                <!-- Users Table -->
                <div class="users-table-container">
                    <div class="table-header">
                        <h3>📋                                                                                                                                                                                                                                                                 <?php echo($user_type === 'teacher' && $teacher_status !== 'admin') ? 'Teachers' : 'Users'; ?> List (<?php echo $total_users; ?> total)</h3>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="bulk_import.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">upload</span>
                                Bulk Import
                            </a>
                            <div class="dropdown" style="position: relative; display: inline-block;">
                                <button class="btn btn-success dropdown-toggle" onclick="toggleExportDropdown()" id="exportDropdown">
                                    <span class="material-symbols-outlined">download</span>
                                    Export Users
                                    <span class="material-symbols-outlined" style="font-size: 16px;">arrow_drop_down</span>
                                </button>
                                <div id="exportDropdownContent" class="dropdown-content" style="display: none; position: absolute; background-color: #f9f9f9; min-width: 160px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1000; right: 0; border-radius: 6px;">
                                    <a href="export_users.php?type=students" style="color: black; padding: 12px 16px; text-decoration: none; display: block;">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">school</span> Export Students
                                    </a>
                                    <a href="export_users.php?type=teachers" style="color: black; padding: 12px 16px; text-decoration: none; display: block;">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span> Export Teachers
                                    </a>
                                    <a href="export_users.php?type=all" style="color: black; padding: 12px 16px; text-decoration: none; display: block;">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">groups</span> Export All Users
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Identifier</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_result && $users_result->num_rows > 0): ?>
                                    <?php while ($user = $users_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                                                        <p><?php echo htmlspecialchars($user['username']); ?></p>
                                                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="user-type-badge type-<?php echo $user['user_type']; ?>">
                                                    <?php echo ucfirst($user['user_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['identifier']); ?></td>
                                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['year_of_join'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo $user['user_type']; ?>', '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['department']); ?>', '<?php echo htmlspecialchars($user['identifier']); ?>')"
                                                            class="btn btn-warning" title="Edit User">
                                                        <span class="material-symbols-outlined">edit</span>
                                                    </button>

                                                    <select onchange="changeUserRole(<?php echo $user['id']; ?>, '<?php echo $user['user_type']; ?>', this.value, '<?php echo $user['status']; ?>')"
                                                            class="btn btn-primary" style="padding: 8px 6px; font-size: 11px; border: none; border-radius: 6px; cursor: pointer;"
                                                            title="Change User Role">
                                                        <?php if ($user['user_type'] === 'teacher'): ?>
                                                            <option value="admin"<?php echo $user['status'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                            <option value="teacher"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo $user['status'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                                        <?php else: ?>
                                                            <option value="student"<?php echo $user['status'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                        <?php endif; ?>
                                                    </select>

                                                    <button onclick="confirmDeleteUser(<?php echo $user['id']; ?>, '<?php echo $user['user_type']; ?>', '<?php echo htmlspecialchars($user['name']); ?>')"
                                                            class="btn btn-danger" title="Delete User">
                                                        <span class="material-symbols-outlined">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px;">
                                            <span class="material-symbols-outlined" style="font-size: 48px; color: #ccc;">person_off</span>
                                            <p style="color: #666; margin: 10px 0;">No                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo($user_type === 'teacher' && $teacher_status !== 'admin') ? 'teachers' : 'users'; ?> found matching your criteria</p>
                                            <a href="add_user.php" class="btn btn-primary">Add First                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo $user_type === 'teacher' ? 'Teacher' : 'User'; ?></a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>&user_type=<?php echo urlencode($filter_user_type); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>&entries=<?php echo urlencode($entries_param); ?>">
                                    « Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&user_type=<?php echo urlencode($filter_user_type); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>&entries=<?php echo urlencode($entries_param); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>&user_type=<?php echo urlencode($filter_user_type); ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>&entries=<?php echo urlencode($entries_param); ?>">
                                    Next »
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>🗑️ Confirm User Deletion</h3>
            <p id="deleteMessage">Are you sure you want to delete this user? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="deleteUser()" class="btn btn-danger">Delete User</button>
            </div>
        </div>
    </div>

    <!-- Role Change Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <h3 id="roleModalTitle">🔄 Change User Role</h3>
            <p id="roleMessage">Are you sure you want to change this user's role?</p>
            <div class="modal-buttons">
                <button onclick="closeRoleModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmRoleChange()" class="btn btn-primary">Change Role</button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 600px; text-align: left;">
            <h3>✏️ Edit User Profile</h3>
            <form id="editForm" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">
                <input type="hidden" name="table_name" id="editTableName">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                    <div class="filter-group">
                        <label for="editName">Full Name *</label>
                        <input type="text" id="editName" name="name" required
                               style="padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px;">
                    </div>

                    <div class="filter-group">
                        <label for="editUsername">Username *</label>
                        <input type="text" id="editUsername" name="username" required
                               style="padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px;">
                    </div>

                    <div class="filter-group">
                        <label for="editEmail">Email *</label>
                        <input type="email" id="editEmail" name="email" required
                               style="padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px;">
                    </div>

                    <div class="filter-group">
                        <label for="editDepartment">Department *</label>
                        <select id="editDepartment" name="department" required
                                style="padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px;">
                            <option value="">Select Department</option>
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
                            <option value="MBA">MBA</option>
                        </select>
                    </div>

                    <div class="filter-group" style="grid-column: 1 / -1;">
                        <label for="editIdentifier" id="editIdentifierLabel">ID *</label>
                        <input type="text" id="editIdentifier" name="identifier" required
                               style="padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; width: 100%;">
                        <small style="color: #666; font-size: 12px;" id="editIdentifierHelp">Registration number for students, Faculty ID for teachers</small>
                    </div>
                </div>

                <div class="modal-buttons" style="text-align: center; margin-top: 30px;">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined" style="font-size: 16px;">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="deleteUserId">
        <input type="hidden" name="table_name" id="deleteTableName">
    </form>

    <form id="roleForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="change_role">
        <input type="hidden" name="user_id" id="roleUserId">
        <input type="hidden" name="table_name" id="roleTableName">
        <input type="hidden" name="new_role" id="newRole">
    </form>

    <script>
        // Modal functions
        let deleteUserId, deleteTableName, roleUserId, roleTableName, newUserRole;

        function confirmDeleteUser(userId, userType, userName) {
            deleteUserId = userId;
            deleteTableName = userType + '_register';

            document.getElementById('deleteMessage').textContent =
                `Are you sure you want to delete ${userName}? This action cannot be undone and will remove all their data from the system.`;
            document.getElementById('deleteModal').style.display = 'block';
        }

        // Edit Modal Functions
        function openEditModal(userId, userType, name, username, email, department, identifier) {
            // Set form values
            document.getElementById('editUserId').value = userId;
            document.getElementById('editTableName').value = userType + '_register';
            document.getElementById('editName').value = name;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editDepartment').value = department;
            document.getElementById('editIdentifier').value = identifier;

            // Update labels based on user type
            const identifierLabel = document.getElementById('editIdentifierLabel');
            const identifierHelp = document.getElementById('editIdentifierHelp');

            if (userType === 'student') {
                identifierLabel.textContent = 'Registration Number *';
                identifierHelp.textContent = 'Student registration number (e.g., 12345678901234)';
            } else {
                identifierLabel.textContent = 'Faculty ID *';
                identifierHelp.textContent = 'Faculty identification number';
            }

            // Show modal
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            // Reset form
            document.getElementById('editForm').reset();
            // Remove any error styling
            const inputs = document.querySelectorAll('#editModal input, #editModal select');
            inputs.forEach(input => {
                input.classList.remove('error');
                const errorMsg = input.parentNode.querySelector('.form-error');
                if (errorMsg) errorMsg.remove();
            });
        }

        // Form validation for edit modal
        document.getElementById('editForm').addEventListener('submit', function(e) {
            let isValid = true;

            // Remove previous error messages
            const errorMessages = document.querySelectorAll('#editModal .form-error');
            errorMessages.forEach(msg => msg.remove());

            // Validate required fields
            const requiredFields = ['editName', 'editUsername', 'editEmail', 'editDepartment', 'editIdentifier'];
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    field.classList.add('error');
                    const errorMsg = document.createElement('span');
                    errorMsg.className = 'form-error';
                    errorMsg.textContent = 'This field is required';
                    field.parentNode.appendChild(errorMsg);
                    isValid = false;
                } else {
                    field.classList.remove('error');
                }
            });

            // Validate email format
            const emailField = document.getElementById('editEmail');
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailField.value && !emailPattern.test(emailField.value)) {
                emailField.classList.add('error');
                let errorMsg = emailField.parentNode.querySelector('.form-error');
                if (errorMsg) {
                    errorMsg.textContent = 'Please enter a valid email address';
                } else {
                    errorMsg = document.createElement('span');
                    errorMsg.className = 'form-error';
                    errorMsg.textContent = 'Please enter a valid email address';
                    emailField.parentNode.appendChild(errorMsg);
                }
                isValid = false;
            }

            // Validate identifier format (basic check)
            const identifierField = document.getElementById('editIdentifier');
            if (identifierField.value && identifierField.value.length < 3) {
                identifierField.classList.add('error');
                let errorMsg = identifierField.parentNode.querySelector('.form-error');
                if (errorMsg) {
                    errorMsg.textContent = 'ID must be at least 3 characters long';
                } else {
                    errorMsg = document.createElement('span');
                    errorMsg.className = 'form-error';
                    errorMsg.textContent = 'ID must be at least 3 characters long';
                    identifierField.parentNode.appendChild(errorMsg);
                }
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                // Focus on first error field
                const firstError = document.querySelector('#editModal .error');
                if (firstError) firstError.focus();
            }
        });

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function deleteUser() {
            document.getElementById('deleteUserId').value = deleteUserId;
            document.getElementById('deleteTableName').value = deleteTableName;
            document.getElementById('deleteForm').submit();
        }

        function changeUserRole(userId, userType, newRole, currentRole) {
            // If no change, do nothing
            if (newRole === currentRole) {
                return;
            }

            roleUserId = userId;
            roleTableName = userType + '_register';
            newUserRole = newRole;

            // Show confirmation modal for role changes
            document.getElementById('roleModalTitle').innerHTML = `🔄 Change User Role`;
            document.getElementById('roleMessage').textContent =
                `Are you sure you want to change this user's role from "${currentRole.charAt(0).toUpperCase() + currentRole.slice(1)}" to "${newRole.charAt(0).toUpperCase() + newRole.slice(1)}"?`;
            document.getElementById('roleModal').style.display = 'block';
        }

        function closeRoleModal() {
            document.getElementById('roleModal').style.display = 'none';
            // Reset all dropdowns to their original values
            location.reload();
        }

        function confirmRoleChange() {
            document.getElementById('roleUserId').value = roleUserId;
            document.getElementById('roleTableName').value = roleTableName;
            document.getElementById('newRole').value = newUserRole;
            document.getElementById('roleForm').submit();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const roleModal = document.getElementById('roleModal');
            const editModal = document.getElementById('editModal');

            if (event.target == deleteModal) {
                deleteModal.style.display = 'none';
            }
            if (event.target == roleModal) {
                roleModal.style.display = 'none';
                location.reload(); // Reset dropdowns
            }
            if (event.target == editModal) {
                closeEditModal();
            }

            // Handle dropdown closing
            if (!event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-toggle')) {
                const dropdowns = document.getElementsByClassName("dropdown-content");
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].style.display = "none";
                }
            }
        }

        // Auto-submit form when changing entries per page
        document.getElementById('entries').addEventListener('change', function() {
            this.form.submit();
        });

        // Search validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const searchInput = document.getElementById('search');
            const searchValue = searchInput.value.trim();

            if (searchValue.length > 0 && searchValue.length < 2) {
                e.preventDefault();
                alert('Search query must be at least 2 characters long.');
                searchInput.focus();
                return false;
            }
        });

        // Real-time search validation feedback
        document.getElementById('search').addEventListener('input', function() {
            const searchValue = this.value.trim();

            if (searchValue.length === 1) {
                this.style.borderColor = '#dc3545';
                this.style.backgroundColor = '#fff5f5';
                this.title = 'Search query must be at least 2 characters long';
            } else {
                this.style.borderColor = '';
                this.style.backgroundColor = '';
                this.title = '';
            }
        });

        // Sidebar functionality
        function navigateToProfile() {
            window.location.href = 'profile.php';
        }

        function closeSidebar() {
            // Add your sidebar close functionality here
        }

        // Export dropdown functionality
        function toggleExportDropdown() {
            const dropdown = document.getElementById("exportDropdownContent");
            dropdown.style.display = dropdown.style.display === "none" ? "block" : "none";
        }
    </script>
</body>
</html>

<?php
    if (isset($users_stmt)) {
        $users_stmt->close();
    }
$conn->close();
?>