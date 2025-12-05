<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

include 'header.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    
    $email = validar_email($_POST['email'] ?? '');
    $newPass = sanitizar_input($_POST['newpass'] ?? '');
    
    if (!$email) {
        $error = 'Email inválido';
    } elseif (!$newPass || strlen($newPass) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres';
    } else {
        $m = 'mutation($email: String!, $newPass: String!) { resetPassword(email: $email, newPass: $newPass) { status message } }';
        $r = graphql_request($m, ['email' => $email, 'newPass' => $newPass], false);
        
        if (!empty($r['errors'])) {
            $error = $r['errors'][0]['message'] ?? 'Error recuperando contraseña';
        } else {
            $resp = $r['data']['resetPassword'] ?? null;
            if ($resp && ($resp['status'] === '200' || $resp['status'] === 200)) {
                $mensaje = 'Contraseña actualizada correctamente. Inicia sesión con la nueva contraseña.';
                header('Refresh:2; url=login_cliente.php');
                exit;
            } else {
                $error = $resp['message'] ?? 'No se pudo actualizar la contraseña';
            }
        }
    }
}

include 'navbar.php';
?>

<div class="container mt-5">
    <h2>Recuperación de cuenta</h2>

    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <?php echo csrf_field(); ?>
        <div class="mb-3">
            <label class="form-label">Correo registrado *</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nueva contraseña *</label>
            <input type="password" name="newpass" class="form-control" required minlength="6">
            <small class="form-text text-muted">Mínimo 6 caracteres</small>
        </div>
        <button class="btn btn-primary" type="submit">Actualizar contraseña</button>
    </form>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>