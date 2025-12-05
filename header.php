<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function sanitizar_input($data) {
    if (is_array($data)) {
        return array_map('sanitizar_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validate_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            // Mostrar error y detener
            die('
            <!DOCTYPE html>
            <html>
            <head>
                <title>Error CSRF</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>
            <body class="bg-light">
                <div class="container mt-5">
                    <div class="alert alert-danger">
                        <h4>Error de seguridad: Token CSRF inválido</h4>
                        <p>Por favor, vuelve a intentarlo.</p>
                        <a href="javascript:history.back()" class="btn btn-secondary">Volver</a>
                    </div>
                </div>
            </body>
            </html>');
        }
    }
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">';
}

function validar_email($email) {
    $email = sanitizar_input($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

function validar_rut($rut) {
    $rut = sanitizar_input($rut);
    return preg_match('/^[0-9]{7,8}-[0-9kK]{1}$/', $rut) ? $rut : false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Natural Power</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    
    <style>
    </style>
</head>
<body>
    <div class="d-flex flex-column min-vh-100">
        <main class="flex-grow-1">