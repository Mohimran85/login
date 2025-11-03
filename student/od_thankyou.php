<?php
    session_start();

    // Check if user is logged in as a student
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OD Request Submitted - Student Portal</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pulse 2s infinite;
        }

        .success-icon .material-symbols-outlined {
            font-size: 50px;
            color: white;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        h1 {
            color: #333;
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .message {
            color: #666;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }

        .details h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .details p {
            color: #666;
            margin-bottom: 10px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff3cd;
            color: #856404;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin: 20px 0;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .success-container {
                padding: 40px 20px;
                margin: 20px;
            }

            h1 {
                font-size: 24px;
            }

            .message {
                font-size: 16px;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <span class="material-symbols-outlined">check_circle</span>
        </div>

        <h1>OD Request Submitted Successfully!</h1>

        <p class="message">
            Your On Duty (OD) request has been submitted and is now pending approval from your class counselor.
            You will be notified once your request is reviewed.
        </p>

        <div class="status-badge">
            <span class="material-symbols-outlined">schedule</span>
            Status: Pending Approval
        </div>

        <div class="details">
            <h3>
                <span class="material-symbols-outlined">info</span>
                What happens next?
            </h3>
            <p><strong>1.</strong> Your class counselor will review your OD request</p>
            <p><strong>2.</strong> You'll receive approval or feedback within 1-2 business days</p>
            <p><strong>3.</strong> Once approved, you can download your official OD letter as PDF</p>
            <p><strong>4.</strong> You can then proceed with event registration using the approved OD</p>
            <p><strong>5.</strong> Check your OD request status anytime in the student portal</p>
        </div>

        <div class="action-buttons">
            <a href="od_request.php" class="btn btn-secondary">
                <span class="material-symbols-outlined">visibility</span>
                View OD Status
            </a>
            <a href="index.php" class="btn btn-primary">
                <span class="material-symbols-outlined">dashboard</span>
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
