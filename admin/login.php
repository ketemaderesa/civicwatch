<?php
// Direct access redirects to the shared, secure administrator sign-in portal.
require_once __DIR__ . '/../includes/functions.php';
redirect('login.php?role=admin');
