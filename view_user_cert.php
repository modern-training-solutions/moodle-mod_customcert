<?php
require_once('../../config.php');

// Get the token parameter
$token = required_param('token', PARAM_ALPHANUMEXT);

require_login();

// Start output buffering to catch any unintended output
ob_start();

// Check if token exists in session and is valid
if (!isset($SESSION->cert_tokens[$token])) {
    ob_end_clean(); // Clean the buffer before throwing exception
    throw new moodle_exception('Invalid or expired token.');
}

$token_data = $SESSION->cert_tokens[$token];
$expiration_time = 300; // 5 minutes in seconds

// Check if token has expired
if ((time() - $token_data['timestamp']) > $expiration_time) {
    unset($SESSION->cert_tokens[$token]); // Clean up expired token
    ob_end_clean();
    throw new moodle_exception('Token has expired. Please try again.');
}

// Find the issued certificate based on the eCard code from token data
$issue = $DB->get_record('customcert_issues', ['code' => $token_data['ecardcode']], '*');

if (!$issue) {
    unset($SESSION->cert_tokens[$token]); // Clean up invalid token
    ob_end_clean();
    throw new moodle_exception('Certificate not found.');
}

// Fetch the certificate linked to this issue
$certificate = $DB->get_record_sql("
    SELECT * FROM {customcert}
    WHERE id = ? AND name IN ('Cognitive eCard', 'Completion eCard')
", [$issue->customcertid]);

if (!$certificate) {
    ob_end_clean();
    throw new moodle_exception('No valid Cognitive eCard or Completion eCard found.');
}

// Clean up the token after successful validation
unset($SESSION->cert_tokens[$token]);

// Get the template
$template = $DB->get_record('customcert_templates', ['id' => $certificate->templateid], '*', MUST_EXIST);
$template = new \mod_customcert\template($template);

// Close session
\core\session\manager::write_close();

// Clean the output buffer before PDF generation
ob_end_clean();

// Generate and output the PDF
$template->generate_pdf(false, $issue->userid);
exit();