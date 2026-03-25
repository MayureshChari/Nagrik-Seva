<?php
session_start();
session_unset();
session_destroy();
header('Location: citizen_login.php');
exit;