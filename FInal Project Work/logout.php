<?php
/* rose.mpawenayo */
require 'db_connect.php';
session_unset();
session_destroy();
header('Location: login.html');
exit;
?>