<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .thank-you-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            text-align: center;
            padding: 20px;
        }

        .thank-you-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 20px;
        }

        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }

        .thank-you-title {
            color: #1e4276;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .thank-you-message {
            color: #6c757d;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e4276 0%, #2d5aa0 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 66, 118, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #1e4276;
            border: 2px solid #1e4276;
        }

        .btn-secondary:hover {
            background: #1e4276;
            color: white;
            transform: translateY(-2px);
        }

        .registration-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }

        .registration-details h4 {
            color: #1e4276;
            margin-bottom: 10px;
        }

        .registration-details p {
            margin: 5px 0;
            color: #495057;
        }

        @media (max-width: 768px) {
            .thank-you-card {
                padding: 30px 20px;
                margin: 10px;
            }

            .thank-you-title {
                font-size: 24px;
            }

            .thank-you-message {
                font-size: 16px;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="icon">
                <img src="./asserts/images/Sona Logo.png" alt="Sona Logo" />
            </div>
            <div class="title">
                <h1>Event Management System</h1>
            </div>
        </div>
    </header>

    <main class="thank-you-container">
        <div class="thank-you-card">
            <div class="success-icon">✅</div>
            <h1 class="thank-you-title">Registration Successful!</h1>
            <p class="thank-you-message">
                Thank you for recording your event participation! Your participation details have been successfully stored in our system.
            </p>
            <div class="action-buttons">
                <a href="student_register.php" class="btn btn-primary">
                    ➕ Register Another Event
                </a>
                <a href="index.php" class="btn btn-secondary">
                    🏠 Back to Home
                </a>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Event Management System. All rights reserved.</p>
    </footer>

    <script>
        // Auto redirect after 30 seconds (optional)
        setTimeout(function() {
            if (confirm('Would you like to register for another event?')) {
                window.location.href = 'student_register.php';
            } else {
                window.location.href = 'index.php';
            }
        }, 30000);

        // Prevent back button to avoid resubmission
        if (window.history && window.history.pushState) {
            window.history.pushState(null, null, window.location.href);
            window.addEventListener('popstate', function () {
                window.history.pushState(null, null, window.location.href);
            });
        }
    </script>
</body>
</html>
