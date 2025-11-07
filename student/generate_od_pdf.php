<?php
    session_start();

    // Check if user is logged in as a student
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get student data
    $username     = $_SESSION['username'];
    $student_data = null;

    $sql  = "SELECT * FROM student_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
    } else {
        header("Location: ../index.php");
        exit();
    }

    // Get OD request ID from URL
    if (! isset($_GET['od_id']) || empty($_GET['od_id'])) {
        header("Location: od_request.php");
        exit();
    }

    $od_id = (int) $_GET['od_id'];

    // Get OD request details with counselor information
    $od_sql = "SELECT odr.*, tr.name as counselor_name, tr.email as counselor_email,
                  tr.faculty_id, tr.department
           FROM od_requests odr
           JOIN teacher_register tr ON odr.counselor_id = tr.id
           WHERE odr.id = ? AND odr.student_regno = ? AND odr.status = 'approved'";
    $od_stmt = $conn->prepare($od_sql);
    $od_stmt->bind_param("is", $od_id, $student_data['regno']);
    $od_stmt->execute();
    $od_result = $od_stmt->get_result();

    if ($od_result->num_rows === 0) {
        header("Location: od_request.php");
        exit();
    }

    $od_data = $od_result->fetch_assoc();
    $od_stmt->close();
    $stmt->close();
    $conn->close();

    // Generate PDF content
    $current_date = date('F d, Y');
    $current_time = date('h:i A');

    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="OD_Letter_' . $student_data['regno'] . '_' . date('Y-m-d') . '.pdf"');

    // Since we don't have external PDF libraries, we'll create an HTML version that can be saved as PDF
    // For a production environment, you would use libraries like TCPDF, FPDF, or mPDF
    // For now, we'll create an HTML version with print styles that browsers can save as PDF

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="OD_Letter_' . $student_data['regno'] . '_' . date('Y-m-d') . '.html"');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OD Letter -                                                                   <?php echo htmlspecialchars($student_data['name']); ?></title>
    <style>
        @media print {
            body {
                margin: 0;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }

        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: white;
            color: #000;
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
        }

        .college-address {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .document-title {
            font-size: 18px;
            font-weight: bold;
            text-decoration: underline;
            margin: 30px 0 20px 0;
            text-align: center;
            color: #0c3878;
        }

        .letter-content {
            max-width: 700px;
            margin: 0 auto;
            font-size: 14px;
        }

        .letter-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .letter-date {
            text-align: right;
        }

        .letter-body {
            margin-bottom: 40px;
        }

        .student-details, .event-details {
            margin: 20px 0;
            background: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #0c3878;
        }

        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }

        .detail-label {
            font-weight: bold;
            width: 150px;
            flex-shrink: 0;
        }

        .detail-value {
            flex: 1;
        }

        .approval-section {
            margin-top: 40px;
            border: 2px solid #28a745;
            background: #f0fff0;
            padding: 20px;
            border-radius: 5px;
        }

        .approval-stamp {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 15px;
        }

        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            text-align: center;
            width: 200px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            height: 50px;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 10px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .download-btn {
            background: #0c3878;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 20px;
            text-decoration: none;
            display: inline-block;
        }

        .download-btn:hover {
            background: #0a2d5a;
        }

        @media screen {
            .print-instructions {
                background: #e3f2fd;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                text-align: center;
                border: 1px solid #2196f3;
            }
        }

        @page {
            margin: 1in;
            size: A4;
        }
    </style>
</head>
<body>
    <div class="print-instructions no-print">
        <p><strong>Instructions:</strong> To save this as PDF, press <kbd>Ctrl+P</kbd> (or <kbd>Cmd+P</kbd> on Mac) and select "Save as PDF" as the destination.</p>
        <button onclick="window.print()" class="download-btn">Print/Save as PDF</button>
        <a href="od_request.php" class="download-btn" style="background: #6c757d;">Back to OD Requests</a>
    </div>

    <div class="letter-content">
        <div class="letterhead">
            <div class="letterhead-content">
                <img src="sona_logo.jpg" alt="Sona College Logo" class="college-logo" >
                <div class="college-info">
                    <div class="college-name">SONA COLLEGE OF TECHNOLOGY</div>
                    <div class="college-address">
                        Salem - 636 005, Tamil Nadu, India<br>
                        Phone: +91-427-2331129 | Email: info@sonatech.ac.in<br>
                        Website: www.sonatech.ac.in
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
                <strong>Date:</strong>                                                                                                                   <?php echo $current_date; ?>
            </div>
        </div>

        <div class="letter-body">
            <p><strong>To Whom It May Concern,</strong></p>

            <p>This is to certify that the following student has been granted On Duty (OD) permission to participate in the mentioned event:</p>

            <div class="student-details">
                <h4 style="margin-top: 0; color: #0c3878;">Student Details:</h4>
                <div class="detail-row">
                    <div class="detail-label">Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($student_data['name']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Register Number:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($student_data['regno']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Course:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($student_data['degree'] ?? 'N/A'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Year of Join:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($student_data['year_of_join'] ?? 'N/A'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Department:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($student_data['department'] ?? 'N/A'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Class Counselor:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($od_data['counselor_name']); ?></div>
                </div>
            </div>

            <div class="event-details">
                <h4 style="margin-top: 0; color: #0c3878;">Event Details:</h4>
                <div class="detail-row">
                    <div class="detail-label">Event Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($od_data['event_name']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Event Date:</div>
                    <div class="detail-value"><?php echo date('F d, Y', strtotime($od_data['event_date'])); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Event Time:</div>
                    <div class="detail-value"><?php echo date('h:i A', strtotime($od_data['event_time'])); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Duration:</div>
                    <div class="detail-value"><?php echo isset($od_data['event_days']) ? htmlspecialchars($od_data['event_days']) . ' day(s)' : 'Not specified'; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Venue:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($od_data['event_location']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Description:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($od_data['event_description']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Reason for OD:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($od_data['reason']); ?></div>
                </div>
            </div>

            <div class="approval-section">
                <div class="approval-stamp">✓ APPROVED</div>
                <div class="detail-row">
                    <div class="detail-label">Approved By:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($od_data['counselor_name']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Approval Date:</div>
                    <div class="detail-value"><?php echo $od_data['response_date'] ? date('F d, Y h:i A', strtotime($od_data['response_date'])) : 'N/A'; ?></div>
                </div>
                <?php if (! empty($od_data['counselor_remarks'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Remarks:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($od_data['counselor_remarks']); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <p style="margin-top: 30px;">
                The student is hereby permitted to attend the above-mentioned event during college hours.
                This letter serves as official documentation for the On Duty permission granted.
            </p>

            <p>
                <strong>Please Note:</strong> This OD letter is valid only for the specified event date and time.
                The student is expected to return to regular classes immediately after the event.
            </p>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div><strong>Class Counselor</strong></div>
                <div><?php echo htmlspecialchars($od_data['counselor_name']); ?></div>
                <div><?php echo htmlspecialchars($od_data['faculty_id']); ?></div>
            </div>

            <div class="signature-box">
                <div class="signature-line"></div>
                <div><strong>Head of Department</strong></div>
                <div><?php echo isset($od_data['department']) ? htmlspecialchars($od_data['department']) : 'Department Name'; ?></div>
            </div>
        </div>

        <div class="footer-note">
            <p>This is a computer-generated document and does not require a physical signature when printed from the official portal.</p>
            <p>Generated on:                                                                                     <?php echo $current_date; ?> at<?php echo $current_time; ?> | Document ID: OD-<?php echo $od_data['id']; ?>-<?php echo date('YmdHis'); ?></p>
        </div>
    </div>

    <script>
        // Auto-print functionality
        function autoPrint() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('auto_print') === 'true') {
                setTimeout(() => {
                    window.print();
                }, 1000);
            }
        }

        // Call auto-print when page loads
        window.addEventListener('load', autoPrint);

        // Enhanced print function
        function printDocument() {
            // Hide print instructions before printing
            const instructions = document.querySelector('.print-instructions');
            if (instructions) {
                instructions.style.display = 'none';
            }

            window.print();

            // Show instructions again after print dialog
            setTimeout(() => {
                if (instructions) {
                    instructions.style.display = 'block';
                }
            }, 1000);
        }
    </script>
</body>
</html>