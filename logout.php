<?php
session_start();
$isTimeout = isset($_GET['timeout']) && $_GET['timeout'] == '1';

session_unset();
session_destroy();

if ($isTimeout) {
    header("Location: login.php?timeout=1");
} else {
    header("Location: login.php");
}
exit;
