<?php
session_start();

// Include QR Code library
require_once 'includes/phpqrcode/qrlib.php';

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
// Allow access if student is the main requester OR a group member
$od_sql = "SELECT odr.*, tr.name as counselor_name, tr.email as counselor_email,
                  tr.faculty_id, tr.department
           FROM od_requests odr
           JOIN teacher_register tr ON odr.counselor_id = tr.id
           WHERE odr.id = ?
           AND (odr.student_regno = ?
                OR FIND_IN_SET(?, REPLACE(odr.group_members, ',', ',')))
           AND odr.status = 'approved'";
$od_stmt = $conn->prepare($od_sql);
$od_stmt->bind_param("iss", $od_id, $student_data['regno'], $student_data['regno']);
$od_stmt->execute();
$od_result = $od_stmt->get_result();

if ($od_result->num_rows === 0) {
    header("Location: od_request.php");
    exit();
}

$od_data = $od_result->fetch_assoc();

// Fetch group members details if this is a group OD (BEFORE closing connection)
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

// Close database connections AFTER fetching all data including group members
$od_stmt->close();
$stmt->close();
$conn->close();

// Generate QR Code for verification (root level - public access)
$verification_url = 'http://' . $_SERVER['HTTP_HOST'] . '/event_management_system/login/verify_od.php?od_id=' . $od_id;
$qr_code_path     = 'uploads/qr_codes/od_' . $od_id . '.png';

// Create QR codes directory if it doesn't exist
if (! is_dir('uploads/qr_codes')) {
    mkdir('uploads/qr_codes', 0777, true);
}

// Generate QR code image
QRcode::png($verification_url, $qr_code_path, 'L', 4, 2);

// Generate PDF content using basic PDF generation
// For production, consider using libraries like TCPDF, FPDF, or mPDF

class SimplePDF
{
    private $content  = '';
    private $filename = '';

    public function __construct($filename = 'document.pdf')
    {
        $this->filename = $filename;
    }

    public function addContent($html)
    {
        $this->content .= $html;
    }

    public function output()
    {
        // Set headers for HTML that can be saved as PDF
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $this->filename . '"');

        echo $this->content;
    }
}

$current_date = date('F d, Y');
$current_time = date('h:i A');
$filename     = 'OD_Letter_' . $student_data['regno'] . '_' . date('Y-m-d') . '.pdf';

$pdf = new SimplePDF($filename);

$html_content = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OD Letter - ' . htmlspecialchars($student_data['name']) . '</title>
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

        .college-logo {
            height: auto;
            margin-bottom: 10px;
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

        .event-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            background: transparent;
            border-left: none;
            padding: 0;
            margin: 12px 0;
        }

        .event-details .section-title {
            grid-column: 1 / -1;
        }

        .event-details-left, .event-details-right {
            background: #f9f9f9;
            padding: 10px;
            border-left: 4px solid #0c3878;
            border-radius: 0 5px 5px 0;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .detail-table td {
            padding: 5px 10px;
            vertical-align: top;
            border-bottom: 1px dotted #ddd;
        }

        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #333;
        }

        .detail-value {
            color: #555;
        }

        .approval-section {
            margin-top: 12px;
            border: 2px solid #28a745;
            background: linear-gradient(135deg, #f0fff0 0%, #e8f5e8 100%);
            padding: 10px;
            border-radius: 10px;
            text-align: center;
        }

        .approval-stamp {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 8px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .approval-details {
            text-align: left;
            margin-top: 8px;
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

        .qr-code-container {
            text-align: center;
            width: 200px;
        }

        .qr-code-container img {
            width: 100px;
            height: 100px;
            border: 2px solid #0c3878;
            padding: 8px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .qr-code-label {
            font-size: 11px;
            color: #0c3878;
            font-weight: bold;
            margin-top: 5px;
        }

        .qr-code-sublabel {
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }

        .footer-note {
            margin-top: 20px;
            font-size: 9px;
            color: #888;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            line-height: 1.3;
        }

        .download-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid #2196f3;
        }

        .download-btn {
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

        .download-btn:hover {
            background: linear-gradient(135deg, #0a2d5a, #0d47a1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(12, 56, 120, 0.4);
        }

        .download-btn.secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .download-btn.secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
        }

        .section-title {
            color: #0c3878;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0c3878;
        }

        .validity-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }

        /* Responsive design for smaller screens */
        @media (max-width: 768px) {
            .event-details {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .event-details-left, .event-details-right {
                padding: 12px;
            }

            .detail-label {
                width: 120px;
                font-size: 13px;
            }

            .detail-table td {
                padding: 4px 8px;
                font-size: 13px;
            }
        }

        @page {
            margin: 0.75in;
            size: A4;
        }

        @media screen {
            .print-only { display: none; }
        }

        @media print {
            .no-print { display: none !important; }
            .print-only { display: block; }

            .event-details {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }

            .event-details-left, .event-details-right {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="download-section no-print">
        <h3 style="margin-top: 0; color: #0c3878;">📄 OD Letter Ready for Download</h3>
        <p style="margin-bottom: 15px;">Your On Duty letter is ready. You can save this as a PDF or print it directly.</p>
        <button onclick="window.print()" class="download-btn">
            🖨️ Print / Save as PDF
        </button>
        <a href="od_request.php" class="download-btn secondary">
            ← Back to OD Requests
        </a>
        <div style="margin-top: 15px; font-size: 12px; color: #666;">
            <strong>Tip:</strong> Press Ctrl+P (or Cmd+P on Mac) and select "Save as PDF" to download this letter.
        </div>
    </div>

    <div class="letter-content">
        <div class="letterhead">
            <div class="letterhead-content">
                <img src="sona_logo.jpg" alt="Sona College Logo" class="college-logo" height="100px"
            width="200" >
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
                <strong>Ref No:</strong> SCT/OD/' . date('Y') . '/' . str_pad($od_data['id'], 4, '0', STR_PAD_LEFT) . '
            </div>
            <div class="letter-date">
                <strong>Date:</strong> ' . $current_date . '
            </div>
        </div>        <div class="letter-body">
            <p><strong>To Whom It May Concern,</strong></p>

            <p style="text-align: justify; line-height: 1.5;">
                This is to certify that <strong>' . htmlspecialchars($student_data['name']) . '</strong>,
                bearing Register Number <strong>' . htmlspecialchars($student_data['regno']) . '</strong>,
                a student of <strong>' . htmlspecialchars($student_data['degree'] ?? 'N/A') . '</strong>

                <strong>' . htmlspecialchars($student_data['department'] ?? 'N/A') . '</strong> department,
                under the guidance of Class Counselor <strong>' . htmlspecialchars($od_data['counselor_name']) . '</strong>, ' . (empty($group_members_details) ? 'has' : 'along with the team members listed below, have') . ' been granted On Duty (OD) permission
                to participate in the mentioned event and ' . (empty($group_members_details) ? 'is' : 'are') . ' hereby authorized to remain OD from regular
                classes for the specified duration.
            </p>';

// Add group members information if this is a group OD
if (! empty($group_members_details)) {
    // Build the list of team members as a comma-separated text
    $member_names = [];
    foreach ($group_members_details as $member) {
        $member_names[] = htmlspecialchars($member['name']) . ' (' . htmlspecialchars($member['regno']) . ')';
    }
    $members_text = implode(', ', $member_names);

    $html_content .= '
            <div class="student-details" style="background: #e3f2fd; border-left-color: #2196f3; padding: 12px 15px;">
                <p style="margin: 0; line-height: 1.6;">
                    <strong style="color: #1976d2;"> GROUP OD:</strong>
                    This is a group participation request. Additional team members: <strong>' . $members_text . '</strong>.
                    Total participants: <strong>' . (count($group_members_details) + 1) . '</strong> (including primary requester).
                </p>
            </div>';
}

$html_content .= '
            <p style="text-align: justify; line-height: 1.5;">
                ' . (empty($group_members_details) ? 'The student is' : 'The students are') . ' permitted to attend <strong>' . htmlspecialchars($od_data['event_name']) . '</strong>,
                scheduled on <strong>' . date('l, F d, Y', strtotime($od_data['event_date'])) . '</strong>
                at <strong>' . date('h:i A', strtotime($od_data['event_time'])) . '</strong>,
                to be held at <strong>' . htmlspecialchars($od_data['event_state']) . ', ' . htmlspecialchars($od_data['event_district']) . '</strong>
                for a duration of <strong>' . (isset($od_data['event_days']) ? htmlspecialchars($od_data['event_days']) . ' day(s)' : 'one day') . '</strong>.
                ' . htmlspecialchars($od_data['event_description']) . '
                The purpose of this OD request is: ' . htmlspecialchars($od_data['reason']) . '.
            </p>

            <p style="margin-top: 15px; text-align: justify; line-height: 1.8;">
                This request has been <strong>officially approved</strong> by <strong>' . htmlspecialchars($od_data['counselor_name']) . '</strong> (Class Counselor) on <strong>' . ($od_data['response_date'] ? date('F d, Y \a\t h:i A', strtotime($od_data['response_date'])) : $current_date) . '</strong>';

if (! empty($od_data['counselor_remarks'])) {
    $html_content .= ' with the following remarks: <em>' . htmlspecialchars($od_data['counselor_remarks']) . '</em>';
}

$html_content .= '. The above-mentioned student has our permission to participate in the stated event. We request your kind cooperation in allowing the student to attend this academic/co-curricular activity. This letter serves as official documentation for the On Duty permission granted by the institution. <strong>Please note:</strong> This OD letter is valid exclusively for the specified event date and duration. The student must resume regular academic activities immediately upon completion of the event.
            </p>
        </div>

        <div class="signature-section">
            <!-- QR Code for verification -->
            <div class="qr-code-container">
                <img src="' . $qr_code_path . '" alt="Verification QR Code">
                <div class="qr-code-label">Scan to Verify</div>
                <div class="qr-code-sublabel">Approved by ' . htmlspecialchars($od_data['counselor_name']) . '</div>
            </div>

            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-title">Head of Department</div>
            </div>
        </div>


    </div>

    <script>
        // Enhanced print functionality
        function printDocument() {
            // Hide download section
            const downloadSection = document.querySelector(".download-section");
            if (downloadSection) {
                downloadSection.style.display = "none";
            }

            // Add print-specific styling
            document.body.classList.add("printing");

            // Trigger print
            window.print();

            // Restore download section after print
            setTimeout(() => {
                if (downloadSection) {
                    downloadSection.style.display = "block";
                }
                document.body.classList.remove("printing");
            }, 1000);
        }

        // Auto-print if requested
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get("auto_print") === "true") {
            setTimeout(printDocument, 1500);
        }

        // Handle keyboard shortcuts
        document.addEventListener("keydown", function(e) {
            // Ctrl/Cmd + P for print
            if ((e.ctrlKey || e.metaKey) && e.key === "p") {
                e.preventDefault();
                printDocument();
            }

            // Escape key to go back
            if (e.key === "Escape") {
                window.location.href = "od_request.php";
            }
        });

        // Add visual feedback for successful generation
        window.addEventListener("load", function() {
            // Show a brief success message
            setTimeout(() => {
                const downloadSection = document.querySelector(".download-section");
                if (downloadSection) {
                    downloadSection.style.animation = "pulse 0.5s ease-in-out";
                }
            }, 500);
        });
    </script>
</body>
</html>';

$pdf->addContent($html_content);
$pdf->output();
