<?php
session_start();
session_destroy();
// Nach Logout zur spendenziele.php
header("Location: spendenziele.php");
exit;
