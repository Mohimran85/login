<?php
    /**
 * Public OD Letter Verification Page
 * This page allows anyone with the OD ID to view approved OD letters
 * Accessed via QR code scanning
 */

    // NO SESSION CHECK - This is a public page
    require_once __DIR__ . '/../includes/db_config.php';

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
                      sr.name as student_name, sr.regno as student_regno, sr.email as student_email,
                      sr.phone as student_phone, sr.department as student_department, sr.degree as student_degree,
                      tr.name as counselor_name, tr.email as counselor_email,
                      tr.faculty_id, tr.department as counselor_department
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
                // Escape each regno for SQL safety
                $escaped_regnos = array_map(function ($regno) use ($conn) {
                    return "'" . $conn->real_escape_string($regno) . "'";
                }, $group_regnos);

                $regnos_list  = implode(',', $escaped_regnos);
                $group_sql    = "SELECT regno, name, department FROM student_register WHERE regno IN ($regnos_list)";
                $group_result = $conn->query($group_sql);

                if ($group_result) {
                    while ($member = $group_result->fetch_assoc()) {
                        $group_members_details[] = $member;
                    }
                }
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
        @media print {
            body {
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }

        body {
            font-family: "Times New Roman", serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: white;
            color: #000;
            font-size: 14px;
        }

        .verification-banner {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .verification-banner h2 {
            margin: 0 0 10px 0;
            font-size: 22px;
        }

        .verification-banner p {
            margin: 0;
            font-size: 14px;
            opacity: 0.95;
        }

        .error-banner {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            margin: 50px auto;
            max-width: 600px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .error-banner h2 {
            margin: 0 0 15px 0;
            font-size: 24px;
        }

        .error-banner p {
            margin: 0;
            font-size: 16px;
            line-height: 1.6;
        }

        .letterhead {
            text-align: center;
            border-bottom: 3px solid #0c3878;
            padding-bottom: 20px;
            margin-bottom: 30px;
            position: relative;
        }

        .letterhead-content {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            position: relative;
            gap: 20px;
        }

        .college-logo {
            width: 120px;
            height: 80px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .college-info {
            flex: 1;
            text-align: center;
        }

        .college-name {
            font-size: 24px;
            font-weight: bold;
            color: #0c3878;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }

        .college-address {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .document-title {
            font-size: 18px;
            font-weight: bold;
            text-decoration: underline;
            margin: 15px 0 15px 0;
            text-align: center;
            color: #0c3878;
            letter-spacing: 2px;
        }

        .letter-content {
            max-width: 700px;
            margin: 0 auto;
        }

        .letter-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 12px;
        }

        .letter-date {
            text-align: right;
        }

        .letter-body {
            margin-bottom: 20px;
            text-align: justify;
        }

        .student-details, .event-details {
            margin: 12px 0;
            background: #f9f9f9;
            padding: 10px;
            border-left: 4px solid #0c3878;
            border-radius: 0 5px 5px 0;
        }

        .signature-section {
            margin-top: 100px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .signature-box {
            text-align: center;
            width: 200px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            height: 40px;
            margin-top: 10px;
        }

        .signature-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
        }

        .signature-name {
            font-size: 11px;
            color: #666;
        }

        .action-buttons {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid #2196f3;
        }

        .btn {
            background: linear-gradient(135deg, #0c3878, #1565c0);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            margin: 10px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(12, 56, 120, 0.3);
        }

        .btn:hover {
            background: linear-gradient(135deg, #0a2d5a, #0d47a1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(12, 56, 120, 0.4);
        }

        @page {
            margin: 0.75in;
            size: A4;
        }
    </style>
</head>
<body>
    <?php if ($error_message): ?>
        <div class="error-banner">
            <h2>❌ Verification Failed</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php else: ?>
        <div class="verification-banner no-print">
            <h2> OD Letter Verified</h2>
            <p>This On Duty letter has been officially approved by Sona College of Technology</p>
            <p style="font-size: 12px; margin-top: 10px;"><strong>Ref No:</strong> SCT/OD/<?php echo date('Y'); ?>/<?php echo str_pad($od_data['id'], 4, '0', STR_PAD_LEFT); ?></p>
        </div>

        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn">🖨️ Print / Save as PDF</button>
        </div>

        <div class="letter-content">
            <div class="letterhead">
                <div class="letterhead-content">
                    <img src="sona_logo.jpg" alt="Sona College Logo" class="college-logo" height="100px" width="200">
                    <div class="college-info">
                        <div class="college-name">SONA COLLEGE OF TECHNOLOGY</div>
                        <div class="college-address">
                            (Autonomous | Affiliated to Anna University)<br>
                            Salem - 636 005, Tamil Nadu, India<br>
                            ☎ +91-427-2331129 | ✉ info@sonatech.ac.in | 🌐 www.sonatech.ac.in<br>
                            NAAC Accredited with A++ Grade | ISO 9001:2015 Certified
                        </div>
                    </div>
                </div>
            </div>

            <div class="document-title">ON DUTY (OD) PERMISSION LETTER</div>

            <div class="letter-header">
                <div class="letter-ref">
                    <strong>Ref No:</strong> SCT/OD/<?php echo date('Y'); ?>/<?php echo str_pad($od_data['id'], 4, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="letter-date">
                    <strong>Date:</strong> <?php echo $current_date; ?>
                </div>
            </div>

            <div class="letter-body">
                <p><strong>To Whom It May Concern,</strong></p>

                <p style="text-align: justify; line-height: 1.5;">
                    This is to certify that <strong><?php echo htmlspecialchars($od_data['student_name']); ?></strong>,
                    bearing Register Number <strong><?php echo htmlspecialchars($od_data['student_regno']); ?></strong>,
                    a student of <strong><?php echo htmlspecialchars($od_data['student_degree'] ?? 'N/A'); ?></strong>
                    <strong><?php echo htmlspecialchars($od_data['student_department'] ?? 'N/A'); ?></strong> department,
                    under the guidance of Class Counselor <strong><?php echo htmlspecialchars($od_data['counselor_name']); ?></strong>,
                    <?php echo empty($group_members_details) ? 'has' : 'along with the team members listed below, have'; ?>
                    been granted On Duty (OD) permission to participate in the mentioned event and
                    <?php echo empty($group_members_details) ? 'is' : 'are'; ?> hereby authorized to remain OD from regular classes for the specified duration.
                </p>

                <?php if (! empty($group_members_details)): ?>
                    <div class="student-details" style="background: #e3f2fd; border-left-color: #2196f3; padding: 12px 15px;">
                        <p style="margin: 0; line-height: 1.6;">
                            <strong style="color: #1976d2;">🔹 GROUP OD:</strong>
                            This is a group participation request. Additional team members:
                            <strong>
                                <?php
                                    $member_names = [];
                                    foreach ($group_members_details as $member) {
                                        $member_names[] = htmlspecialchars($member['name']) . ' (' . htmlspecialchars($member['regno']) . ')';
                                    }
                                    echo implode(', ', $member_names);
                                ?>
                            </strong>.
                            Total participants: <strong><?php echo count($group_members_details) + 1; ?></strong> (including primary requester).
                        </p>
                    </div>
                <?php endif; ?>

                <p style="text-align: justify; line-height: 1.5;">
                    <?php echo empty($group_members_details) ? 'The student is' : 'The students are'; ?>
                    permitted to attend <strong><?php echo htmlspecialchars($od_data['event_name']); ?></strong>,
                    scheduled on <strong><?php echo date('l, F d, Y', strtotime($od_data['event_date'])); ?></strong>
                    at <strong><?php echo date('h:i A', strtotime($od_data['event_time'])); ?></strong>,
                    to be held at <strong><?php echo htmlspecialchars($od_data['event_state']) . ', ' . htmlspecialchars($od_data['event_district']); ?></strong>
                    for a duration of <strong><?php echo isset($od_data['event_days']) ? htmlspecialchars($od_data['event_days']) . ' day(s)' : 'one day'; ?></strong>.
                    <?php echo htmlspecialchars($od_data['event_description']); ?>
                    The purpose of this OD request is: <?php echo htmlspecialchars($od_data['reason']); ?>.
                </p>

                <p style="margin-top: 15px; text-align: justify; line-height: 1.8;">
                    This request has been <strong>officially approved</strong> by
                    <strong><?php echo htmlspecialchars($od_data['counselor_name']); ?></strong> (Class Counselor)
                    on <strong><?php echo $od_data['response_date'] ? date('F d, Y \a\t h:i A', strtotime($od_data['response_date'])) : $current_date; ?></strong><?php if (! empty($od_data['counselor_remarks'])): ?> with the following remarks:
                    <em><?php echo htmlspecialchars($od_data['counselor_remarks']); ?></em><?php endif; ?>.
                    The above-mentioned student has our permission to participate in the stated event.
                    We request your kind cooperation in allowing the student to attend this academic/co-curricular activity.
                    This letter serves as official documentation for the On Duty permission granted by the institution.
                    <strong>Please note:</strong> This OD letter is valid exclusively for the specified event date and duration.
                    The student must resume regular academic activities immediately upon completion of the event.
                </p>
            </div>

            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-title">Class Counselor</div>
                    <div class="signature-name"><?php echo htmlspecialchars($od_data['counselor_name']); ?></div>
                </div>

                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-title">Head of Department</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>