<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

include 'header.php';

$mensaje = '';
$error = '';

if (isset($_SESSION['usuario']) && ($_SESSION['usuario']['perfilTipo'] ?? '') === 'Empleado') {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    
    $email = validar_email($_POST['email'] ?? '');
    $pass = sanitizar_input($_POST['pass'] ?? '');

    if (!$email) {
        $error = 'Email inválido';
    } elseif (!$pass || strlen($pass) < 1) {
        $error = 'Contraseña requerida';
    } else {
        $mutation = '
            mutation Login($email: String!, $pass: String!) {
                login(email: $email, pass: $pass) {
                    token
                    usuario {
                        id
                        nombre
                        email
                        perfilTipo
                        perfil {
                            ... on Cliente {
                                rut
                                nombre
                                email
                                estado
                            }
                            ... on Empleado {
                                rut
                                nombre
                                email
                                cargo
                            }
                        }
                    }
                }
            }';
        $vars = ['email' => $email, 'pass' => $pass];
        $resp = graphql_request($mutation, $vars, false);

        if (!empty($resp['errors'])) {
            $error = $resp['errors'][0]['message'] ?? 'Error de login';
        } else {
            $data = $resp['data']['login'] ?? null;
            if ($data) {
                $usuario = $data['usuario'] ?? null;
                
                if (!empty($usuario) && ($usuario['perfilTipo'] ?? '') === 'Empleado') {
                    unset($_SESSION['cliente']);
                    
                    $_SESSION['token'] = $data['token'] ?? null;
                    $_SESSION['usuario'] = $usuario;
                    header('Location: admin.php');
                    exit;
                } else {
                    $error = 'Este usuario no tiene permisos de empleado';
                }
            } else {
                $error = 'Credenciales incorrectas';
            }
        }
    }
}

include 'navbar.php';
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h3>Ingreso empleado</h3>
            <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="pass" class="form-control" required>
                </div>
                <button class="btn btn-primary">Ingresar</button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>