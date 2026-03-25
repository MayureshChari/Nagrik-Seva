<?php
// clear_otp_session.php — clears the OTP email from session
session_start();
unset($_SESSION['otp_email']);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);