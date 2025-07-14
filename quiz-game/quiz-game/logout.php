<?php
session_start();
unset($_SESSION['user_id']);
unset($_SESSION['current_attempt_id']);
session_destroy();
header("Location: login");
exit;