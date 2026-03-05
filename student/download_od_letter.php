<?php
session_start();

// Include QR Code library
require_once 'includes/phpqrcode/qrlib.php';

// Include Composer autoloader for dompdf
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in as a student
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

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
$qr_code_path     = __DIR__ . '/uploads/qr_codes/od_' . $od_id . '.png';

// Create QR codes directory if it doesn't exist
if (! is_dir(__DIR__ . '/uploads/qr_codes')) {
    mkdir(__DIR__ . '/uploads/qr_codes', 0777, true);
}

// Generate QR code image
QRcode::png($verification_url, $qr_code_path, 'L', 4, 2);

// Helper: Convert image file to base64 data URI for embedding in PDF
function imageToDataUri($filepath)
{
    if (file_exists($filepath)) {
        $type = pathinfo($filepath, PATHINFO_EXTENSION);
        $mime = ($type === 'png') ? 'image/png' : 'image/jpeg';
        $data = base64_encode(file_get_contents($filepath));
        return 'data:' . $mime . ';base64,' . $data;
    }
    return '';
}

$logo_data_uri = imageToDataUri(__DIR__ . '/sona_logo.jpg');
$qr_data_uri   = imageToDataUri($qr_code_path);

$current_date = date('F d, Y');
$current_time = date('h:i A');
$filename     = 'OD_Letter_' . $student_data['regno'] . '_' . date('Y-m-d') . '.pdf';

// Build group members HTML
$group_html = '';
if (! empty($group_members_details)) {
    $member_names = [];
    foreach ($group_members_details as $member) {
        $member_names[] = htmlspecialchars($member['name']) . ' (' . htmlspecialchars($member['regno']) . ')';
    }
    $members_text = implode(', ', $member_names);

    $group_html = '
        <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px 15px; margin: 12px 0;">
            <p style="margin: 0; line-height: 1.6;">
                <strong style="color: #1976d2;">GROUP OD:</strong>
                This is a group participation request. Additional team members: <strong>' . $members_text . '</strong>.
                Total participants: <strong>' . (count($group_members_details) + 1) . '</strong> (including primary requester).
            </p>
        </div>';
}

// Build counselor remarks
$remarks_html = '';
if (! empty($od_data['counselor_remarks'])) {
    $remarks_html = ' with the following remarks: <em>' . htmlspecialchars($od_data['counselor_remarks']) . '</em>';
}

$html_content = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OD Letter - ' . htmlspecialchars($student_data['name']) . '</title>
    <style>
        @page {
            margin: 60px 50px;
            size: A4 portrait;
        }

        body {
            font-family: "Times New Roman", Times, serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background: white;
            color: #000;
            font-size: 13px;
        }

        .letterhead-table {
            width: 100%;
            border-bottom: 3px solid #0c3878;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .college-name {
            font-size: 22px;
            font-weight: bold;
            color: #0c3878;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }

        .college-address {
            font-size: 11px;
            color: #666;
            line-height: 1.4;
        }

        .document-title {
            font-size: 17px;
            font-weight: bold;
            text-decoration: underline;
            margin: 15px 0;
            text-align: center;
            color: #0c3878;
            letter-spacing: 2px;
        }

        .ref-date-table {
            width: 100%;
            margin-bottom: 15px;
            font-size: 12px;
        }

        .letter-body {
            margin-bottom: 20px;
            text-align: justify;
        }

        .letter-body p {
            text-align: justify;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .group-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px 15px;
            margin: 12px 0;
        }

        .approval-box {
            border: 2px solid #28a745;
            background: #f0fff0;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }

        .approval-stamp {
            font-size: 16px;
            font-weight: bold;
            color: #28a745;
        }

        .signature-table {
            width: 100%;
            margin-top: 80px;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 180px;
            margin: 0 auto;
            padding-top: 5px;
        }

        .qr-label {
            font-size: 10px;
            color: #0c3878;
            font-weight: bold;
            text-align: center;
        }

        .qr-sublabel {
            font-size: 9px;
            color: #666;
            text-align: center;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 9px;
            color: #888;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <!-- Letterhead -->
    <table class="letterhead-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 130px; vertical-align: middle; padding-right: 15px;">
                ' . ($logo_data_uri ? '<img src="' . $logo_data_uri . '" width="120" />' : '') . '
            </td>
            <td style="text-align: center; vertical-align: middle;">
                <div class="college-name">SONA COLLEGE OF TECHNOLOGY</div>
                <div class="college-address">
                    (Autonomous | Affiliated to Anna University)<br>
                    Salem - 636 005, Tamil Nadu, India<br>
                    +91-427-2331129 | info@sonatech.ac.in | www.sonatech.ac.in<br>
                    NAAC Accredited with A++ Grade | ISO 9001:2015 Certified
                </div>
            </td>
        </tr>
    </table>

    <div class="document-title">ON DUTY (OD) PERMISSION LETTER</div>

    <!-- Ref No and Date -->
    <table class="ref-date-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="text-align: left;">
                <strong>Ref No:</strong> SCT/OD/' . date('Y') . '/' . str_pad($od_data['id'], 4, '0', STR_PAD_LEFT) . '
            </td>
            <td style="text-align: right;">
                <strong>Date:</strong> ' . $current_date . '
            </td>
        </tr>
    </table>

    <!-- Letter Body -->
    <div class="letter-body">
        <p><strong>To Whom It May Concern,</strong></p>

        <p>
            This is to certify that <strong>' . htmlspecialchars($student_data['name']) . '</strong>,
            bearing Register Number <strong>' . htmlspecialchars($student_data['regno']) . '</strong>,
            a student of <strong>' . htmlspecialchars($student_data['degree'] ?? 'N/A') . '</strong>
            <strong>' . htmlspecialchars($student_data['department'] ?? 'N/A') . '</strong> department,
            under the guidance of Class Counselor <strong>' . htmlspecialchars($od_data['counselor_name']) . '</strong>,
            ' . (empty($group_members_details) ? 'has' : 'along with the team members listed below, have') . ' been granted On Duty (OD) permission
            to participate in the mentioned event and ' . (empty($group_members_details) ? 'is' : 'are') . ' hereby authorized to remain OD from regular
            classes for the specified duration.
        </p>

        ' . $group_html . '

        <p>
            ' . (empty($group_members_details) ? 'The student is' : 'The students are') . ' permitted to attend <strong>' . htmlspecialchars($od_data['event_name']) . '</strong>,
            scheduled on <strong>' . date('l, F d, Y', strtotime($od_data['event_date'])) . '</strong>
            at <strong>' . date('h:i A', strtotime($od_data['event_time'])) . '</strong>,
            to be held at <strong>' . htmlspecialchars($od_data['event_state']) . ', ' . htmlspecialchars($od_data['event_district']) . '</strong>
            for a duration of <strong>' . (isset($od_data['event_days']) ? htmlspecialchars($od_data['event_days']) . ' day(s)' : 'one day') . '</strong>.
            ' . htmlspecialchars($od_data['event_description']) . '
            The purpose of this OD request is: ' . htmlspecialchars($od_data['reason']) . '.
        </p>

        <p>
            This request has been <strong>officially approved</strong> by
            <strong>' . htmlspecialchars($od_data['counselor_name']) . '</strong> (Class Counselor) on
            <strong>' . ($od_data['response_date'] ? date('F d, Y \a\t h:i A', strtotime($od_data['response_date'])) : $current_date) . '</strong>'
. $remarks_html .
'. The above-mentioned student has our permission to participate in the stated event.
            We request your kind cooperation in allowing the student to attend this academic/co-curricular activity.
            This letter serves as official documentation for the On Duty permission granted by the institution.
            <strong>Please note:</strong> This OD letter is valid exclusively for the specified event date and duration.
            The student must resume regular academic activities immediately upon completion of the event.
        </p>
    </div>

    <!-- Signature Section -->
    <table class="signature-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 40%; text-align: center; vertical-align: bottom;">
                ' . ($qr_data_uri ? '<img src="' . $qr_data_uri . '" width="90" height="90" style="border: 2px solid #0c3878; padding: 5px;" />' : '') . '
                <div class="qr-label">Scan to Verify</div>
                <div class="qr-sublabel">Approved by ' . htmlspecialchars($od_data['counselor_name']) . '</div>
            </td>
            <td style="width: 20%;">&nbsp;</td>
            <td style="width: 40%; text-align: center; vertical-align: bottom;">
                <div class="signature-line"></div>
                <strong style="font-size: 12px;">Head of Department</strong>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        This is a computer-generated document. The QR code can be scanned to verify authenticity.<br>
        Generated on ' . $current_date . ' at ' . $current_time . ' | Ref: SCT/OD/' . date('Y') . '/' . str_pad($od_data['id'], 4, '0', STR_PAD_LEFT) . '
    </div>

</body>
</html>';

// Generate actual PDF using Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Times New Roman');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html_content);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Stream PDF as a downloadable file (works in median.co / WebView apps)
$dompdf->stream($filename, ['Attachment' => true]);
