<?php
    /**
 * Public OD Letter Verification Page
 * This page allows anyone with the OD ID to view approved OD letters
 * Accessed via QR code scanning - NO AUTHENTICATION REQUIRED
 */

    // NO SESSION CHECK - This is a public page
    require_once 'includes/db_config.php';

    $conn = get_db_connection();

    // Get OD request ID from URL
    if (! isset($_GET['od_id']) || empty($_GET['od_id'])) {
    $error_message = "Invalid OD Letter request. Please scan the QR code again.";
    $od_data       = null;
    } else {
    $od_id = (int) $_GET['od_id'];

    // Get OD request details with student and counselor information
    // Only show APPROVED OD letters for security
    $od_sql = "SELECT odr.*,
                      sr.name as student_name, sr.regno as student_regno,
                      sr.department as student_department, sr.degree as student_degree,
                      tr.name as counselor_name, tr.faculty_id, tr.department as counselor_department
               FROM od_requests odr
               JOIN student_register sr ON odr.student_regno = sr.regno
               JOIN teacher_register tr ON odr.counselor_id = tr.id
               WHERE odr.id = ? AND odr.status = 'approved'";
    $od_stmt = $conn->prepare($od_sql);
    $od_stmt->bind_param("i", $od_id);
    $od_stmt->execute();
    $od_result = $od_stmt->get_result();

    if ($od_result->num_rows === 0) {
        $error_message = "OD Letter not found or not yet approved. Please contact the student or college administration.";
        $od_data       = null;
    } else {
        $od_data       = $od_result->fetch_assoc();
        $error_message = null;

        // Fetch group members details if this is a group OD
        $group_members_details = [];
        if (! empty($od_data['group_members'])) {
            $group_regnos = array_filter(array_map('trim', explode(',', $od_data['group_members'])));

            if (! empty($group_regnos)) {
                $placeholders = implode(',', array_fill(0, count($group_regnos), '?'));
                $types        = str_repeat('s', count($group_regnos));
                $group_sql    = "SELECT regno, name, department FROM student_register WHERE regno IN ($placeholders)";
                $group_stmt   = $conn->prepare($group_sql);
                $group_stmt->bind_param($types, ...$group_regnos);
                $group_stmt->execute();
                $group_result = $group_stmt->get_result();

                if ($group_result) {
                    while ($member = $group_result->fetch_assoc()) {
                        $group_members_details[] = $member;
                    }
                }
                $group_stmt->close();
            }
        }
    }

    if (isset($od_stmt)) {
        $od_stmt->close();
    }
    }

    $conn->close();

    $current_date = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $od_data ? 'OD Letter Verification - ' . htmlspecialchars($od_data['student_name']) : 'OD Letter Not Found'; ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
        }

        /* Verification Banner */
        .verification-banner {
            background:linear-gradient(135deg, #0c3878, #03285e);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.25);
        }

        .verification-banner h2 { font-size: 20px; margin-bottom: 5px; font-weight: 700; }
        .verification-banner p { font-size: 13px; opacity: 0.95; }
        .verification-banner .ref { font-size: 12px; margin-top: 8px; background: rgba(255,255,255,0.2); display: inline-block; padding: 3px 12px; border-radius: 20px; }

        .error-banner {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
            margin: 40px auto;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.25);
        }

        .error-banner h2 { font-size: 22px; margin-bottom: 10px; }
        .error-banner p { font-size: 15px; }

        /* Card */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 18px;
            overflow: hidden;
        }
        .card-header {
            padding: 14px 20px;
            font-weight: 700;
            font-size: 15px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-body { padding: 0; }

        .card-header.student { background: #0c3878; }
        .card-header.event { background: #1565c0; }
        .card-header.approval { background: #28a745; }
        .card-header.group { background: #6f42c1; }

        /* Detail Tables */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .detail-table tr { border-bottom: 1px solid #f0f0f0; }
        .detail-table tr:last-child { border-bottom: none; }
        .detail-table td {
            padding: 10px 20px;
            font-size: 14px;
            vertical-align: top;
        }
        .detail-table .label {
            font-weight: 600;
            color: #555;
            width: 40%;
            background: #fafbfc;
        }
        .detail-table .value {
            color: #222;
        }

        /* Group Members Table */
        .members-table {
            width: 100%;
            border-collapse: collapse;
        }
        .members-table th {
            background: #f5f0ff;
            color: #4a2d8a;
            padding: 10px 16px;
            font-size: 13px;
            text-align: left;
            font-weight: 600;
        }
        .members-table td {
            padding: 10px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
            word-break: break-word;
        }
        .members-table tr:last-child td { border-bottom: none; }
        .members-table .sno { width: 40px; text-align: center; }
        .members-table .primary-badge {
            background: #0c3878;
            color: #fff;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 2px;
            font-weight: 600;
        }

        /* Approval Status */
        .status-badge {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
        }
        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Remarks */
        .remarks-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 16px;
            margin: 0 20px 15px;
            border-radius: 0 6px 6px 0;
            font-size: 13px;
            color: #856404;
            font-style: italic;
        }

        /* Footer */
        .footer-note {
            text-align: center;
            font-size: 11px;
            color: #999;
            margin-top: 20px;
            padding: 15px;
        }

        /* Print */
        @media print {
            body { background: #fff; padding: 10px; }
            .no-print { display: none !important; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }

        @media (max-width: 480px) {
            body { padding: 10px; }
            .detail-table .label { width: 45%; }
            .detail-table td { padding: 8px 12px; font-size: 13px; }

            /* Stack group members table on small screens */
            .members-table thead { display: none; }
            .members-table, .members-table tbody, .members-table tr, .members-table td {
                display: block;
                width: 100%;
            }
            .members-table tr {
                padding: 12px 16px;
                border-bottom: 1px solid #f0f0f0;
            }
            .members-table tr:last-child { border-bottom: none; }
            .members-table td {
                padding: 3px 0;
                border-bottom: none;
                font-size: 13px;
            }
            .members-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #555;
                margin-right: 8px;
            }
            .members-table .sno { display: none; }
            .members-table .primary-badge { margin-left: 4px; }
        }
    </style>
</head>
<body>
    <?php if ($error_message): ?>
        <div class="error-banner">
            <h2>Verification Failed</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php else: ?>
    <div class="container">

        <!-- Verification Banner -->
        <div class="verification-banner">

            <h2>OD Letter Verified Successfully</h2>
            <p>This On Duty letter has been officially approved by Sona College of Technology</p>
            <div class="ref">Ref: SCT/OD/<?php echo date('Y'); ?>/<?php echo str_pad($od_data['id'], 4, '0', STR_PAD_LEFT); ?></div>
        </div>

        <!-- Student Details Card -->
        <div class="card">
            <div class="card-header student">
                 Student Details
            </div>
            <div class="card-body">
                <table class="detail-table">
                    <tr>
                        <td class="label">Student Name</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['student_name']); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Register Number</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['student_regno']); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Degree</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['student_degree'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Department</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['student_department'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Class Counselor</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['counselor_name']); ?> (<?php echo htmlspecialchars($od_data['counselor_department']); ?>)</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Group Members Card (if any) -->
        <?php if (! empty($group_members_details)): ?>
        <div class="card">
            <div class="card-header group">
                 Group Members (<?php echo count($group_members_details) + 1; ?> participants)
            </div>
            <div class="card-body">
                <table class="members-table">
                    <thead>
                        <tr>
                            <th class="sno">S.No</th>
                            <th>Name</th>
                            <th>Reg. No</th>
                            <th>Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="sno">1</td>
                            <td data-label="Name: "><?php echo htmlspecialchars($od_data['student_name']); ?> <span class="primary-badge">PRIMARY</span></td>
                            <td data-label="Reg. No: "><?php echo htmlspecialchars($od_data['student_regno']); ?></td>
                            <td data-label="Dept: "><?php echo htmlspecialchars($od_data['student_department'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php $count = 2;foreach ($group_members_details as $member): ?>
                        <tr>
                            <td class="sno"><?php echo $count++; ?></td>
                            <td data-label="Name: "><?php echo htmlspecialchars($member['name']); ?></td>
                            <td data-label="Reg. No: "><?php echo htmlspecialchars($member['regno']); ?></td>
                            <td data-label="Dept: "><?php echo htmlspecialchars($member['department'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Event Details Card -->
        <div class="card">
            <div class="card-header event">
                Event Details
            </div>
            <div class="card-body">
                <table class="detail-table">
                    <tr>
                        <td class="label">Event Name</td>
                        <td class="value"><strong><?php echo htmlspecialchars($od_data['event_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="label">Event Date</td>
                        <td class="value"><?php echo date('l, F d, Y', strtotime($od_data['event_date'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Event Time</td>
                        <td class="value"><?php echo date('h:i A', strtotime($od_data['event_time'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Duration</td>
                        <td class="value"><?php echo isset($od_data['event_days']) ? htmlspecialchars($od_data['event_days']) . ' day(s)' : '1 day'; ?></td>
                    </tr>
                    <tr>
                        <td class="label">Venue / Location</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['event_state']) . ', ' . htmlspecialchars($od_data['event_district']); ?></td>
                    </tr>
                    <?php if (! empty($od_data['event_description'])): ?>
                    <tr>
                        <td class="label">Description</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['event_description']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="label">Reason for OD</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['reason']); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Approval Details Card -->
        <div class="card">
            <div class="card-header approval">
                Approval Details
            </div>
            <div class="card-body">
                <table class="detail-table">
                    <tr>
                        <td class="label">Status</td>
                        <td class="value"><span class="status-badge status-approved">APPROVED</span></td>
                    </tr>
                    <tr>
                        <td class="label">Approved By</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['counselor_name']); ?> (Class Counselor)</td>
                    </tr>
                    <tr>
                        <td class="label">Faculty ID</td>
                        <td class="value"><?php echo htmlspecialchars($od_data['faculty_id'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Approved On</td>
                        <td class="value"><?php echo $od_data['response_date'] ? date('F d, Y \a\t h:i A', strtotime($od_data['response_date'])) : $current_date; ?></td>
                    </tr>
                </table>
                <?php if (! empty($od_data['counselor_remarks'])): ?>
                <div class="remarks-box">
                    <strong>Counselor Remarks:</strong> <?php echo htmlspecialchars($od_data['counselor_remarks']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-note">
            This is a digitally verified document from Sona College of Technology.<br>
            Ref: SCT/OD/<?php echo date('Y'); ?>/<?php echo str_pad($od_data['id'], 4, '0', STR_PAD_LEFT); ?> |
            Verified on: <?php echo $current_date; ?>
        </div>

    </div>
    <?php endif; ?>
</body>
</html>