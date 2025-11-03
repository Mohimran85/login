<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Poster Upload - Event Management System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .test-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        h1 {
            color: #0c3878;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .feature-list {
            text-align: left;
            margin: 30px 0;
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
        }

        .feature-list h3 {
            color: #0c3878;
            margin-bottom: 15px;
        }

        .feature-list ul {
            margin: 0;
            padding-left: 20px;
        }

        .feature-list li {
            margin-bottom: 10px;
            color: #495057;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #0c3878;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px;
            transition: background 0.3s ease;
            font-weight: 500;
        }

        .btn:hover {
            background: #2d5aa0;
        }

        .btn.success {
            background: #28a745;
        }

        .btn.success:hover {
            background: #218838;
        }

        .status-section {
            margin: 30px 0;
            padding: 20px;
            background: #e8f5e8;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }

        .upload-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🎉 OD Request System - Enhanced!</h1>

        <div class="status-section">
            <h3>✅ System Status: Ready!</h3>
            <p>Your enhanced OD request system is now fully functional with poster upload capabilities.</p>
        </div>

        <div class="feature-list">
            <h3>🚀 New Features Added:</h3>
            <ul>
                <li><strong>Event Poster Upload:</strong> Students can now upload event posters (images or PDFs)</li>
                <li><strong>Thumbnail Preview:</strong> Quick preview of uploaded posters in the request list</li>
                <li><strong>Poster Viewer:</strong> Dedicated viewer with download and fullscreen options</li>
                <li><strong>Duration Field:</strong> Specify number of days for the event</li>
                <li><strong>Enhanced PDF:</strong> Professional OD letters with college logo and branding</li>
                <li><strong>File Security:</strong> Secure upload handling with size and type validation</li>
                <li><strong>Mobile Responsive:</strong> Works perfectly on all devices</li>
            </ul>
        </div>

        <div class="upload-info">
            <strong>📁 Upload Requirements:</strong><br>
            • File types: JPG, PNG, PDF<br>
            • Maximum size: 5MB<br>
            • Files stored securely in uploads/posters/
        </div>

        <div>
            <a href="od_request.php" class="btn success">Start Using OD Request System</a>
            <a href="index.php" class="btn">Back to Dashboard</a>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 14px;">
            <p><strong>Instructions:</strong></p>
            <ol style="text-align: left; margin: 10px 0;">
                <li>Fill out the OD request form with event details</li>
                <li>Upload an event poster (optional but recommended)</li>
                <li>Submit your request for counselor approval</li>
                <li>Once approved, download your official OD letter</li>
                <li>Register for the event participation</li>
            </ol>
        </div>
    </div>
</body>
</html>
