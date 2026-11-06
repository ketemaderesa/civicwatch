<?php
require_once __DIR__ . '/includes/functions.php';

if (!is_post()) {
    flash('info', 'Use the sign-out button to end your session.');
    redirect(auth_user() ? dashboard_path(auth_user()['role']) : 'login.php');
}

require_csrf();
$role = auth_user()['role'] ?? null;
logout_user();

// Start a fresh anonymous session so the confirmation flash can be displayed.
session_start();
session_regenerate_id(true);
flash('success', 'You have been signed out securely.');
redirect($role ? 'login.php' : 'index.php');
