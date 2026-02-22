<?php
    session_start();
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/DatabaseManager.php';
    require_once __DIR__ . '/../includes/csrf.php';

    // Prevent caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Require student authentication
    requireAuth('../index.php');

    $db       = DatabaseManager::getInstance();
    $username = $_SESSION['username'];

    // Get student info
    $student_query = "SELECT regno, name, department FROM student_register WHERE username = ? LIMIT 1";
    $student_data  = $db->executeQuery($student_query, [$username]);
    $student_regno = $student_data[0]['regno'];
    $student_name  = $student_data[0]['name'];
    $student_dept  = $student_data[0]['department'];

    // Get hackathon ID
    $hackathon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (! $hackathon_id) {
    header("Location: hackathons.php");
    exit();
    }

    // Get hackathon details
    $hackathon_sql = "SELECT hp.*,
    COUNT(DISTINCT CASE WHEN ha.status = 'confirmed' THEN ha.id END) as confirmed_applications,
    CASE WHEN EXISTS (
        SELECT 1 FROM hackathon_applications
        WHERE hackathon_id = hp.id AND student_regno = ?
    ) THEN 1 ELSE 0 END as has_applied
    FROM hackathon_posts hp
    LEFT JOIN hackathon_applications ha ON hp.id = ha.hackathon_id
    WHERE hp.id = ? AND hp.status IN ('upcoming', 'ongoing')
    GROUP BY hp.id";

    $hackathons = $db->executeQuery($hackathon_sql, [$student_regno, $hackathon_id]);

    if (empty($hackathons)) {
    $_SESSION['error_message'] = "Hackathon not found or not available.";
    header("Location: hackathons.php");
    exit();
    }

    $hackathon = $hackathons[0];

    // Check if already applied
    if ($hackathon['has_applied']) {
    $_SESSION['error_message'] = "You have already applied for this hackathon.";
    header("Location: hackathon_details.php?id=" . $hackathon_id);
    exit();
    }

    // Check deadline
    $deadline   = strtotime($hackathon['registration_deadline']);
    $now        = time();
    $is_expired = $deadline < $now;

    if ($is_expired) {
    $_SESSION['error_message'] = "Registration deadline has passed.";
    header("Location: hackathon_details.php?id=" . $hackathon_id);
    exit();
    }

    $errors  = [];
    $success = false;

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (! validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        $application_type    = $_POST['application_type'] ?? 'individual';
        $team_name           = trim($_POST['team_name'] ?? '');
        $team_members        = trim($_POST['team_members'] ?? '');
        $project_description = trim($_POST['project_description'] ?? '');

        // Validation
        if (! in_array($application_type, ['individual', 'team'])) {
            $errors[] = "Invalid application type";
        }

        if ($application_type === 'team') {
            if (empty($team_name)) {
                $errors[] = "Team name is required for team applications";
            }
            if (empty($team_members)) {
                $errors[] = "Please provide team member details";
            }
        }

        if (empty($project_description)) {
            $errors[] = "Project description is required";
        } elseif (strlen($project_description) < 50) {
            $errors[] = "Project description must be at least 50 characters";
        }

        // Parse team members JSON if team application
        $team_members_json = null;
        if ($application_type === 'team' && ! empty($team_members)) {
            try {
                // Parse team members from textarea (expected format: Name (Regno), one per line)
                $lines   = explode("\n", $team_members);
                $members = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    // Try to parse: "Name (Regno)" or "Name - Regno"
                    if (preg_match('/^(.+?)\s*[\(\-]\s*([A-Z0-9]+)\s*[\)]?\s*$/i', $line, $matches)) {
                        $members[] = [
                            'name'  => trim($matches[1]),
                            'regno' => trim($matches[2]),
                        ];
                    } else {
                        $members[] = [
                            'name'  => $line,
                            'regno' => '',
                        ];
                    }
                }

                $team_members_json = json_encode($members);
            } catch (Exception $e) {
                $errors[] = "Invalid team member format";
            }
        }

        // Check if still within limits (race condition protection)
        if (empty($errors)) {
            $conn = $db->getConnection();
            $conn->begin_transaction();

            try {
                // Lock hackathon record
                $check_sql = "SELECT id FROM hackathon_posts WHERE id = ? FOR UPDATE";
                $stmt      = $conn->prepare($check_sql);
                $stmt->bind_param("i", $hackathon_id);
                $stmt->execute();
                $stmt->close();

                // Check double application
                $check_app_sql = "SELECT id FROM hackathon_applications
                                  WHERE hackathon_id = ? AND student_regno = ?";
                $stmt = $conn->prepare($check_app_sql);
                $stmt->bind_param("is", $hackathon_id, $student_regno);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    throw new Exception("You have already applied");
                }
                $stmt->close();

                // Insert application
                $insert_sql = "INSERT INTO hackathon_applications
                    (hackathon_id, student_regno, application_type, team_name, team_members, project_description, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'confirmed')";

                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("isssss",
                    $hackathon_id,
                    $student_regno,
                    $application_type,
                    $team_name,
                    $team_members_json,
                    $project_description
                );

                if ($stmt->execute()) {
                    $conn->commit();
                    $success = true;

                    // Redirect to details page with success message
                    $_SESSION['success_message'] = "Application submitted successfully!";
                    header("Location: hackathon_details.php?id=" . $hackathon_id);
                    exit();
                } else {
                    throw new Exception("Failed to submit application");
                }

            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
    }

    // Generate CSRF token
    $csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply - <?php echo htmlspecialchars($hackathon['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7f6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .back-button {
            background: #1a408c;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: none;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 64, 140, 0.4);
            background: #15306b;
        }

        .form-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .form-header .hackathon-title {
            font-size: 18px;
            color: #1a408c;
            font-weight: 600;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef5350;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #66bb6a;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a408c;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .radio-option {
            flex: 1;
            position: relative;
        }

        .radio-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .radio-option label {
            display: block;
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .radio-option input[type="radio"]:checked + label {
            border-color: #1a408c;
            background: #f0f4f8;
            color: #1a408c;
            font-weight: 600;
        }

        .radio-option label .material-symbols-outlined {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
        }

        .team-fields {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }

        .team-fields.active {
            display: block;
        }

        .btn {
            padding: 15px 40px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: #1a408c;
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(26, 64, 140, 0.4);
            background: #15306b;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #666;
        }

        .form-footer {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .char-count {
            float: right;
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .form-container {
                padding: 25px;
            }

            .radio-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="hackathon_details.php?id=<?php echo $hackathon_id; ?>" class="back-button">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Details
        </a>

        <div class="form-container">
            <div class="form-header">
                <h1>
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 32px; color: #1a408c;">rocket_launch</span>
                    Apply for Hackathon
                </h1>
                <p class="hackathon-title"><?php echo htmlspecialchars($hackathon['title']); ?></p>
            </div>

            <?php if (! empty($errors)): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin: 5px 0 0 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" id="applicationForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Application Type -->
                <div class="form-group">
                    <label>Application Type <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="individual" name="application_type" value="individual" checked>
                            <label for="individual">
                                <span class="material-symbols-outlined">person</span>
                                Individual
                                <small style="display:block; margin-top: 5px; font-weight: 400; color: #666;">
                                    Apply solo
                                </small>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="team" name="application_type" value="team">
                            <label for="team">
                                <span class="material-symbols-outlined">groups</span>
                                Team
                                <small style="display:block; margin-top: 5px; font-weight: 400; color: #666;">
                                    Apply with team
                                </small>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Team Fields (conditional) -->
                <div id="teamFields" class="team-fields">
                    <div class="form-group">
                        <label for="team_name">Team Name <span class="required">*</span></label>
                        <input type="text" id="team_name" name="team_name"
                               placeholder="e.g., Code Warriors"
                               value="<?php echo htmlspecialchars($_POST['team_name'] ?? ''); ?>">
                        <small>Enter a creative name for your team</small>
                    </div>

                    <div class="form-group">
                        <label for="team_members">Team Members <span class="required">*</span></label>
                        <textarea id="team_members" name="team_members"
                                  placeholder="Enter team member details (one per line)&#10;Format: Name (Regno)&#10;&#10;Example:&#10;John Doe (CS101)&#10;Jane Smith (CS102)&#10;Bob Wilson (CS103)"
                        ><?php echo htmlspecialchars($_POST['team_members'] ?? ''); ?></textarea>
                        <small>Include yourself and all team members. Format: Name (RegNo) - one per line</small>
                    </div>
                </div>

                <!-- Project Description -->
                <div class="form-group">
                    <label for="project_description">
                        Project Description <span class="required">*</span>
                        <span class="char-count">
                            <span id="charCount">0</span> / 50 minimum
                        </span>
                    </label>
                    <textarea id="project_description" name="project_description"
                              placeholder="Describe what you plan to build for this hackathon:&#10;• Problem you're solving&#10;• Your solution approach&#10;• Technologies you'll use&#10;• Expected outcome&#10;&#10;Minimum 50 characters required."
                              required
                              minlength="50"
                    ><?php echo htmlspecialchars($_POST['project_description'] ?? ''); ?></textarea>
                    <small>Describe your project idea, approach, technologies, and expected outcome (minimum 50 characters)</small>
                </div>

                <!-- Form Actions -->
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">send</span>
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle team fields based on application type
        const individualRadio = document.getElementById('individual');
        const teamRadio = document.getElementById('team');
        const teamFields = document.getElementById('teamFields');
        const teamNameInput = document.getElementById('team_name');
        const teamMembersInput = document.getElementById('team_members');

        function toggleTeamFields() {
            if (teamRadio.checked) {
                teamFields.classList.add('active');
                teamNameInput.required = true;
                teamMembersInput.required = true;
            } else {
                teamFields.classList.remove('active');
                teamNameInput.required = false;
                teamMembersInput.required = false;
            }
        }

        individualRadio.addEventListener('change', toggleTeamFields);
        teamRadio.addEventListener('change', toggleTeamFields);

        // Character counter
        const projectDesc = document.getElementById('project_description');
        const charCount = document.getElementById('charCount');

        projectDesc.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            charCount.style.color = count >= 50 ? '#2e7d32' : '#e74c3c';
        });

        // Initialize character count
        projectDesc.dispatchEvent(new Event('input'));

        // Form validation
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            const description = projectDesc.value.trim();

            if (description.length < 50) {
                e.preventDefault();
                alert('Project description must be at least 50 characters long.');
                projectDesc.focus();
                return false;
            }

            if (teamRadio.checked) {
                const teamName = teamNameInput.value.trim();
                const teamMembers = teamMembersInput.value.trim();

                if (!teamName) {
                    e.preventDefault();
                    alert('Please enter your team name.');
                    teamNameInput.focus();
                    return false;
                }

                if (!teamMembers) {
                    e.preventDefault();
                    alert('Please enter team member details.');
                    teamMembersInput.focus();
                    return false;
                }
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Submitting...';
        });
    </script>
</body>
</html>
