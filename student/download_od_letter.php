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

// Get OD request details with counselor information and digital signature
$od_sql = "SELECT odr.*, tr.name as counselor_name, tr.email as counselor_email,
                  tr.faculty_id, tr.department, ts.signature_type, ts.signature_data,
                  ts.signature_hash, ts.created_at as signature_created
           FROM od_requests odr
           JOIN teacher_register tr ON odr.counselor_id = tr.id
           LEFT JOIN teacher_signatures ts ON tr.id = ts.teacher_id AND ts.is_active = TRUE
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

// Check if counselor has a digital signature
$has_digital_signature       = ! empty($od_data['signature_data']);
$signature_verification_code = '';

if ($has_digital_signature) {
    // Generate verification code for this specific OD letter
    $signature_verification_code = hash('sha256', $od_data['signature_hash'] . $od_id . $od_data['student_regno']);
}

$od_stmt->close();
$stmt->close();
$conn->close();

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
            margin: 30px 0 20px 0;
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
            margin-bottom: 30px;
            font-size: 12px;
        }

        .letter-date {
            text-align: right;
        }

        .letter-body {
            margin-bottom: 40px;
            text-align: justify;
        }

        .student-details, .event-details {
            margin: 20px 0;
            background: #f9f9f9;
            padding: 15px;
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
            margin-top: 40px;
            border: 2px solid #28a745;
            background: linear-gradient(135deg, #f0fff0 0%, #e8f5e8 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .approval-stamp {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 15px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .approval-details {
            text-align: left;
            margin-top: 15px;
        }

        .signature-section {
            margin-top: 60px;
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
            height: 60px;
            margin-top: 20px;
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

        .footer-note {
            margin-top: 40px;
            font-size: 9px;
            color: #888;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 15px;
            line-height: 1.4;
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

            <p>This is to certify that the following student of our institution has been granted <strong>On Duty (OD) permission</strong> to participate in the mentioned event. The student is hereby authorized to remain absent from regular classes for the specified duration.</p>

            <div class="student-details">
                <div class="section-title">STUDENT INFORMATION</div>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Student Name:</td>
                        <td class="detail-value">' . htmlspecialchars($student_data['name']) . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Register Number:</td>
                        <td class="detail-value">' . htmlspecialchars($student_data['regno']) . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Course:</td>
                        <td class="detail-value">' . htmlspecialchars($student_data['degree'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Year of Join:</td>
                        <td class="detail-value">' . htmlspecialchars($student_data['year_of_join'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Department:</td>
                        <td class="detail-value">' . htmlspecialchars($student_data['department'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Class Counselor:</td>
                        <td class="detail-value">' . htmlspecialchars($od_data['counselor_name']) . ' (' . htmlspecialchars($od_data['faculty_id']) . ')</td>
                    </tr>
                </table>
            </div>

            <div class="event-details">
                <div class="section-title">EVENT INFORMATION</div>
                <table class="detail-table">
                    <tr>
                        <td class="detail-label">Event Name:</td>
                        <td class="detail-value"><strong>' . htmlspecialchars($od_data['event_name']) . '</strong></td>
                    </tr>
                    <tr>
                        <td class="detail-label">Event Date:</td>
                        <td class="detail-value">' . date('l, F d, Y', strtotime($od_data['event_date'])) . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Event Time:</td>
                        <td class="detail-value">' . date('h:i A', strtotime($od_data['event_time'])) . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Duration:</td>
                        <td class="detail-value">' . (isset($od_data['event_days']) ? htmlspecialchars($od_data['event_days']) . ' day(s)' : 'Not specified') . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Venue:</td>
                        <td class="detail-value">' . htmlspecialchars($od_data['event_location']) . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Description:</td>
                        <td class="detail-value">' . htmlspecialchars($od_data['event_description']) . '</td>
                    </tr>
                    <tr>
                        <td class="detail-label">Purpose:</td>
                        <td class="detail-value">' . htmlspecialchars($od_data['reason']) . '</td>
                    </tr>
                </table>
            </div>

            <div class="approval-section">
                <div class="approval-stamp">✅ OFFICIALLY APPROVED</div>
                <div class="approval-details">
                    <table class="detail-table" style="margin: 0;">
                        <tr>
                            <td class="detail-label">Approved By:</td>
                            <td class="detail-value">' . htmlspecialchars($od_data['counselor_name']) . '</td>
                        </tr>
                        <tr>
                            <td class="detail-label">Designation:</td>
                            <td class="detail-value">Class Counselor</td>
                        </tr>
                        <tr>
                            <td class="detail-label">Approval Date:</td>
                            <td class="detail-value">' . ($od_data['response_date'] ? date('F d, Y \a\t h:i A', strtotime($od_data['response_date'])) : $current_date) . '</td>
                        </tr>';

if (! empty($od_data['counselor_remarks'])) {
    $html_content .= '
                        <tr>
                            <td class="detail-label">Remarks:</td>
                            <td class="detail-value">' . htmlspecialchars($od_data['counselor_remarks']) . '</td>
                        </tr>';
}

$html_content .= '
                    </table>
                </div>
            </div>

            <div class="validity-notice">
                <strong>⚠️ Important Note:</strong> This OD letter is valid exclusively for the specified event date and duration. The student must resume regular academic activities immediately upon completion of the event. Any extension requires separate approval.
            </div>

            <p style="margin-top: 30px; text-align: justify;">
                The above-mentioned student has our permission to participate in the stated event. We request your kind cooperation in allowing the student to attend this academic/co-curricular activity. This letter serves as official documentation for the On Duty permission granted by the institution.
            </p>
        </div>

        <div class="signature-section">
            <div class="signature-box">';

if ($has_digital_signature) {
    $html_content .= '<div class="digital-signature">';

    if ($od_data['signature_type'] === 'upload' || $od_data['signature_type'] === 'drawn') {
        $html_content .= '<img src="../teacher/' . htmlspecialchars($od_data['signature_data']) . '"
                             alt="Digital Signature"
                             style="max-width: 200px; max-height: 80px; border: 1px solid #ddd; background: white;">';
    } elseif ($od_data['signature_type'] === 'text') {
        $text_data = json_decode($od_data['signature_data'], true);
        $html_content .= '<div style="font-family: ' . htmlspecialchars($text_data['font']) . ';
                            font-size: 24px; color: #000; padding: 10px;
                            border: 1px solid #ddd; background: white; min-height: 60px;
                            display: flex; align-items: center; justify-content: center;">
                            ' . htmlspecialchars($text_data['text']) . '
                          </div>';
    }

    $html_content .= '<div style="font-size: 10px; color: #666; margin-top: 5px;">
                        🔐 Digitally Signed | Verification: ' . substr($signature_verification_code, 0, 12) . '...
                      </div></div>';
} else {
    $html_content .= '<div class="signature-line"></div>';
}

$html_content .= '
                <div class="signature-title">Class Counselor</div>
                <div class="signature-name">' . htmlspecialchars($od_data['counselor_name']) . '</div>
                <div class="signature-name">' . htmlspecialchars($od_data['faculty_id']) . '</div>
            </div>

            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-title">Head of Department</div>
                <div class="signature-name">' . (isset($od_data['department']) ? htmlspecialchars($od_data['department']) : 'Department Name') . '</div>
            </div>
        </div>

        <div class="footer-note">
            <p><strong>Note:</strong> This is a digitally generated document from the official Event Management System.</p>';

if ($has_digital_signature) {
    $html_content .= '<p><strong>Digital Security:</strong> This document contains a verified digital signature.
                        Verification Code: ' . $signature_verification_code . '</p>';
}

$html_content .= '
            <p>🔐 Document Authentication: OD-' . $od_data['id'] . '-' . date('YmdHis') . ' | Generated: ' . $current_date . ' at ' . $current_time . '</p>
            <p>📧 For verification, contact: info@sonatech.ac.in | ☎ +91-427-2331129</p>
            <p style="margin-top: 10px; font-style: italic;">Sona College of Technology - Nurturing Excellence Since 1997</p>
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
