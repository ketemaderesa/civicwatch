<?php
// Direct access redirects to the shared, secure staff sign-in portal.
require_once __DIR__ . '/../includes/functions.php';
redirect('login.php?role=staff');
