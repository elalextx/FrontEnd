<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['token'])) {
    require_once 'graphql.php';
    $mutation = 'mutation($token: String!) { logout(token: $token) { status message } }';
    graphql_request($mutation, ['token' => $_SESSION['token']], true);
}

session_destroy();
header('Location: index.php');
exit;
?>