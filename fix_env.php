<?php
$envContent = "ONESIGNAL_APP_ID=29fbebb0-954f-41f3-8f31-c3f57f61740b
ONESIGNAL_REST_API_KEY=os_v2_app_fh56xmevj5a7hdzryp2x6y1ubptc5izlig2ek45d2w4s3dermy6zrw7okofdf4rbk2vrho3u4iahukbdk564r1u7gkr4jkdstenyprq
";

file_put_contents(__DIR__ . "/.env", $envContent);
echo "✓ .env file fixed!\n";
echo "Content:\n";
echo htmlspecialchars(file_get_contents(__DIR__ . "/.env"));
