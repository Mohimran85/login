<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    // Get poster filename from URL
    if (! isset($_GET['poster']) || empty($_GET['poster'])) {
        header("Location: od_request.php");
        exit();
    }

    $poster_file = basename($_GET['poster']); // Sanitize filename
    $poster_path = 'uploads/posters/' . $poster_file;

    // Check if file exists
    if (! file_exists($poster_path)) {
        header("Location: od_request.php");
        exit();
    }

    // Get file extension to determine content type
    $file_extension = strtolower(pathinfo($poster_path, PATHINFO_EXTENSION));

    // Set appropriate content type
    switch ($file_extension) {
        case 'jpg':
        case 'jpeg':
            $content_type = 'image/jpeg';
            break;
        case 'png':
            $content_type = 'image/png';
            break;
        case 'pdf':
            $content_type = 'application/pdf';
            break;
        default:
            header("Location: od_request.php");
            exit();
    }

    // For images, show in a nice viewer
    if (in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Poster - Event Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        .header {
            background: white;
            width: 100%;
            max-width: 1200px;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #0c3878;
            font-size: 18px;
            font-weight: 600;
        }

        .back-btn {
            background: #0c3878;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: #2d5aa0;
        }

        .poster-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            width: 100%;
            text-align: center;
        }

        .poster-image {
            max-width: 100%;
            max-height: 80vh;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .poster-actions {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .action-btn.download {
            background: #28a745;
            color: white;
        }

        .action-btn.download:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .action-btn.fullscreen {
            background: #6c757d;
            color: white;
        }

        .action-btn.fullscreen:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .poster-container {
                padding: 20px;
            }

            .poster-actions {
                flex-direction: column;
                align-items: center;
            }

            .action-btn {
                width: 100%;
                max-width: 200px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 Event Poster Viewer</h1>
        <a href="od_request.php" class="back-btn">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to OD Requests
        </a>
    </div>

    <div class="poster-container">
        <img src="<?php echo htmlspecialchars($poster_path); ?>" alt="Event Poster" class="poster-image">

        <div class="poster-actions">
            <a href="<?php echo htmlspecialchars($poster_path); ?>" download class="action-btn download">
                <span class="material-symbols-outlined">download</span>
                Download Poster
            </a>
            <a href="<?php echo htmlspecialchars($poster_path); ?>" target="_blank" class="action-btn fullscreen">
                <span class="material-symbols-outlined">fullscreen</span>
                View Full Size
            </a>
        </div>
    </div>

    <script>
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to go back
            if (e.key === 'Escape') {
                window.location.href = 'od_request.php';
            }

            // F key for fullscreen
            if (e.key === 'f' || e.key === 'F') {
                window.open('<?php echo htmlspecialchars($poster_path); ?>', '_blank');
            }
        });
    </script>
</body>
</html>
<?php
    } else {
        // For PDFs, serve directly
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: inline; filename="' . $poster_file . '"');
        readfile($poster_path);
}
?>