<?php
// Load configuration
$config_file = '../config.js';
$config_content = file_get_contents($config_file);
// Extract reCAPTCHA secret key from config (you'll need to add this)
// For now, define it here:
define('RECAPTCHA_SECRET_KEY', '#################'); // Add your secret key here
define('CONTACT_EMAIL', '###########@###.###'); // Your email
define('SITE_NAME', '#######');
define('SITE_DOMAIN', '#########.net');

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify reCAPTCHA
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
if (empty($recaptcha_response)) {
    echo json_encode(['success' => false, 'message' => 'Please complete the reCAPTCHA verification']);
    exit;
}

// Verify with Google
$recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
$recaptcha_data = [
    'secret' => RECAPTCHA_SECRET_KEY,
    'response' => $recaptcha_response,
    'remoteip' => $_SERVER['REMOTE_ADDR']
];
$recaptcha_options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($recaptcha_data)
    ]
];
$recaptcha_context = stream_context_create($recaptcha_options);
$recaptcha_result = file_get_contents($recaptcha_url, false, $recaptcha_context);
$recaptcha_json = json_decode($recaptcha_result);

if (!$recaptcha_json->success) {
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed. Please try again.']);
    exit;
}

// Sanitize and validate input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Get form data
$username = sanitize_input($_POST['username'] ?? '');
$email    = sanitize_input($_POST['email'] ?? '');
$name     = sanitize_input($_POST['name'] ?? '');
$subject  = sanitize_input($_POST['subject'] ?? '');
$message  = sanitize_input($_POST['message'] ?? '');

// Validation
$errors = [];
if (empty($username) || strlen($username) < 2) {
    $errors[] = 'Username must be at least 2 characters long';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email address is required';
}
if (empty($name) || strlen($name) < 2) {
    $errors[] = 'Name must be at least 2 characters long';
}
if (empty($subject)) {
    $errors[] = 'Subject is required';
}
if (empty($message) || strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters long';
}
if (strlen($message) > 2000) {
    $errors[] = 'Message is too long (max 2000 characters)';
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
    exit;
}

// Rate limiting (simple IP-based)
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_file = sys_get_temp_dir() . '/contact_' . md5($ip);
$rate_limit_time = 300; // 5 minutes

if (file_exists($rate_limit_file)) {
    $last_submit = filemtime($rate_limit_file);
    if (time() - $last_submit < $rate_limit_time) {
        $wait = $rate_limit_time - (time() - $last_submit);
        echo json_encode([
            'success' => false,
            'message' => "Please wait {$wait} seconds before submitting another message."
        ]);
        exit;
    }
}

// Subject mapping
$subject_labels = [
    'general'   => 'General Inquiry',
    'technical' => 'Technical Support',
    'bug'       => 'Bug Report',
    'feature'   => 'Feature Request',
    'account'   => 'Account Issue',
    'billing'   => 'Billing Question',
    'other'     => 'Other'
];
$subject_label = $subject_labels[$subject] ?? 'Contact Form';

// ────────────────────────────────────────────────
//        CLEAN NEUTRAL-COLOR EMAIL TEMPLATE
// ────────────────────────────────────────────────
$email_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Submission</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        h2 {
            color: #2d3748;
            text-align: center;
            margin-bottom: 20px;
        }
        .field {
            margin-bottom: 15px;
        }
        .label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .value {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            word-wrap: break-word;
        }
        .message {
            white-space: pre-wrap;
        }
        .separator {
            border-top: 1px solid #ddd;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            font-style: italic;
            color: #777;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>New Support Form Submission from #######.net</h2>
       
        <div class="field">
            <span class="label">Username:</span>
            <div class="value">$username</div>
        </div>
       
        <div class="field">
            <span class="label">Name:</span>
            <div class="value">$name</div>
        </div>
       
        <div class="field">
            <span class="label">Email:</span>
            <div class="value"><a href="mailto:$email">$email</a></div>
        </div>
       
        <div class="field">
            <span class="label">Subject:</span>
            <div class="value">$subject_label</div>
        </div>
       
        <div class="field">
            <span class="label">Message:</span>
            <div class="value message">$message</div>
        </div>
       
        <div class="separator"></div>
       
        <div class="footer">You can reply directly to this email.<br>— ####### Support Team</div>
    </div>
</body>
</html>
HTML;

// Determine from email (must be on your domain for better deliverability)
$from_email = "########@lrgame.net";

// Email headers for support notification
$headers = [];
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/html; charset=UTF-8";
$headers[] = "From: " . SITE_NAME . " <{$from_email}>";
$headers[] = "Reply-To: {$name} <{$email}>";
$headers[] = "X-Mailer: PHP/" . phpversion();
$headers[] = "X-Priority: 3";

// Email subject
$email_subject = SITE_NAME . " - {$subject_label}";

// Try to send email to support
$mail_sent = @mail(CONTACT_EMAIL, $email_subject, $email_body, implode("\r\n", $headers));

// Log submission
$log_entry = date('Y-m-d H:i:s') . " - {$username} ({$email}) - {$subject_label} - " . ($mail_sent ? "SUCCESS" : "FAILED") . "\n";
@file_put_contents('contact_log.txt', $log_entry, FILE_APPEND);

// Backup regardless of send status
$backup_data = "\n\n=== SUBMISSION ===\n";
$backup_data .= "Date: " . date('Y-m-d H:i:s') . "\n";
$backup_data .= "Username: {$username}\n";
$backup_data .= "Name: {$name}\n";
$backup_data .= "Email: {$email}\n";
$backup_data .= "Subject: {$subject_label}\n";
$backup_data .= "Message: {$message}\n";
$backup_data .= "IP: {$ip}\n";
$backup_data .= "Status: " . ($mail_sent ? "Sent" : "Failed") . "\n";
@file_put_contents('contact_submissions.txt', $backup_data, FILE_APPEND);

if ($mail_sent) {
    // Update rate limit
    touch($rate_limit_file);

    // ────────────────────────────────────────────────
    //              AUTO-REPLY (same style!)
    // ────────────────────────────────────────────────
    $user_subject = "We've Received Your Message – #####";
    
    $user_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You – #######.net</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        h2 {
            color: #2d3748;
            text-align: center;
            margin-bottom: 20px;
        }
        .field {
            margin-bottom: 15px;
        }
        .label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .value {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            word-wrap: break-word;
        }
        .message {
            white-space: pre-wrap;
            background-color: #f9f9f9;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .separator {
            border-top: 1px solid #ddd;
            margin: 25px 0;
        }
        .footer {
            text-align: center;
            font-style: italic;
            color: #777;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Thank You for Contacting ########</h2>
        
        <p>Hi {$name},</p>
        <p>We have received your message and our support team will get back to you within 24-48 hours.</p>
        
        <div class="separator"></div>
        
        <div class="field">
            <span class="label">Your Message:</span>
            <div class="message">{$message}</div>
        </div>
        
        <div class="separator"></div>
        
        <p style="text-align:center;">Best regards,<br><strong>######</strong></p>
        
        <div class="footer">
            ####### – Support Team
        </div>
    </div>
</body>
</html>
HTML;

    $user_headers = [];
    $user_headers[] = "MIME-Version: 1.0";
    $user_headers[] = "Content-Type: text/html; charset=UTF-8";
    $user_headers[] = "From: " . SITE_NAME . " <{$from_email}>";
    $user_headers[] = "Reply-To: " . CONTACT_EMAIL;

    @mail($email, $user_subject, $user_body, implode("\r\n", $user_headers));

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your message has been sent successfully. We\'ll get back to you within 24-48 hours.'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Your message has been received and saved! We\'ll review it and contact you at ' . $email . ' soon.'
    ]);
}
?>