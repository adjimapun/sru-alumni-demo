<?php
require __DIR__.'/config.php';
unset($_SESSION['user_id']); session_regenerate_id(true);
header('Location: index.php'); exit;
