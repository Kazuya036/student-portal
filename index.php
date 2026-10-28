<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
redirect(current_user() ? 'app.php' : 'login.php');
