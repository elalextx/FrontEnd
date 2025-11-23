<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$mensaje = '';
$error = '';

if (isset($_SESSION['cliente'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    if (!$email || !$pass) {
        $error = 'Email y contraseña son requeridos';
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
                
                if (!empty($usuario) && ($usuario['perfilTipo'] ?? '') === 'Cliente') {
                    $perfil = $usuario['perfil'] ?? [];
                    
                    unset($_SESSION['usuario']);
                    unset($_SESSION['token']);
                    
                    $_SESSION['cliente'] = [
                        'id' => $usuario['id'],
                        'nombre' => $perfil['nombre'] ?? $usuario['nombre'],
                        'email' => $usuario['email'],
                        'rut' => $perfil['rut'] ?? '',
                        'estado' => $perfil['estado'] ?? 'activo'
                    ];
                    
                    if (!empty($perfil['rut'])) {
                        $mCarrito = 'mutation($cid:String!) { crearCarrito(clienteId:$cid){ id } }';
                        graphql_request($mCarrito, ['cid' => $perfil['rut']], false);
                    }
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Este usuario no es un cliente. Use el login de empleados.';
                }
            } else {
                $error = 'Credenciales incorrectas';
            }
        }
    }
}

include 'header.php';
include 'navbar.php';
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h3>Ingreso cliente</h3>
            <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Contraseña</label>
                    <input type="password" name="pass" class="form-control" required>
                </div>
                <button class="btn btn-primary">Ingresar</button>
            </form>
            <div class="mt-3">
                <a href="recuperacion.php">¿Olvidaste tu contraseña?</a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>