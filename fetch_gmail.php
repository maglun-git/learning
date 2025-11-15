<?php
// fetch_gmail.php - env-driven
// Reads mail credentials from environment variables (or a .env loaded by dbconnect.php)
// IMPORTANT: Do not hard-code passwords in the repository. Use .env.example as a template and add .env to .gitignore.

include "dbconnect.php"; // loads env and provides $connection
require_once __DIR__ . '/lib/ImapFetcher.php';

dotenv('GMAIL_USER', getenv('GMAIL_USER'));  
dotenv('GMAIL_PASS', getenv('GMAIL_PASS'));  
dotenv('ATTACHMENT_COMPANY_ID', getenv('ATTACHMENT_COMPANY_ID') ?: 1);  
dotenv('ATTACHMENT_UPLOAD_DIR', getenv('ATTACHMENT_UPLOAD_DIR') ?: __DIR__ . '/uploads');

if (empty($gmailUser) || empty($gmailPass)) {
    error_log('GMAIL_USER or GMAIL_PASS not set. Aborting fetch_gmail.');
    die('Mail fetch not configured. Please set GMAIL_USER and GMAIL_PASS in environment.');
}

// Ensure upload dir exists with restrictive permissions
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        error_log("Failed to create upload dir: $uploadDir");
        die('Failed to create upload directory.');
    }
}

// Create IMAP fetcher
$fetcher = new ImapFetcher($gmailUser, $gmailPass);
try {
    $mails = $fetcher->fetchUnread();
} catch (Exception $e) {
    error_log('IMAP fetch error: ' . $e->getMessage());
    die('Failed to fetch mail.');
}

foreach ($mails as $mail) {
    $messageId = isset($mail['message_id']) ? (string)$mail['message_id'] : null;
    if (empty($mail['attachments']) || !is_array($mail['attachments'])) {
        continue;
    }
    foreach ($mail['attachments'] as $att) {
        $originalFilename = isset($att['filename']) ? $att['filename'] : 'attachment';
        $safeBase = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $originalFilename);
        $safeName = time() . '_' . $safeBase;
        $targetPath = rtrim($uploadDir, '/') . '/' . $safeName;

        if (file_put_contents($targetPath, $att['content']) === false) {
            error_log('Failed saving attachment to ' . $targetPath);
            continue;
        }

        // Insert metadata into DB
        $stmt = $connection->prepare(
            "INSERT INTO attachments (company_id, gmail_message_id, filename, mime_type, file_path) VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            error_log('DB prepare failed: ' . $connection->error);
            continue;
        }

        $companyIdInt = (int)$companyId;
        $msgId = $messageId ?? '';
        $mime = isset($att['mime']) ? $att['mime'] : '';
        // store relative path to project root if possible
        $relativePath = str_replace(__DIR__ . '/', '', $targetPath);

        $stmt->bind_param('issss', $companyIdInt, $msgId, $originalFilename, $mime, $relativePath);
        if (!$stmt->execute()) {
            error_log('DB insert failed: ' . $stmt->error);
        }
        $stmt->close();
    }
}

echo "Done fetching.";
?>