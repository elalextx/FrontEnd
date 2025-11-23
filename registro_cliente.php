<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    if (!$rut || !$nombre || !$email || !$pass) {
        $error = 'Todos los campos son obligatorios';
    } else {
        $m = 'mutation($rut:String!, $nombre:String!, $email:String!, $pass:String!) { 
            addCliente(rut:$rut, nombre:$nombre, email:$email, pass:$pass) { 
                id 
                rut 
                nombre 
                email 
                estado 
            } 
        }';
        
        $r = graphql_request($m, [
            'rut' => $rut,
            'nombre' => $nombre,
            'email' => $email,
            'pass' => $pass
        ], false);
        
        if (!empty($r['errors'])) {
            $error = $r['errors'][0]['message'] ?? 'Error al registrar cliente';
        } else if (isset($r['data']['addCliente'])) {
            $cliente = $r['data']['addCliente'];
            
            $m2 = 'mutation($cid:String!) { 
                crearCarrito(clienteId:$cid) { 
                    id 
                } 
            }';
            $r2 = graphql_request($m2, ['cid' => $cliente['rut']], false);
            
            $mensaje = 'Registro exitoso. Redirigiendo...';
            header('Refresh:1; url=index.php');
            exit;
        } else {
            $error = 'Error inesperado al registrar cliente';
        }
    }
}

include 'header.php';
include 'navbar.php';
?>
<div class="container mt-5">
    <h2>Registro de Cliente</h2>
    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label>RUT</label>
            <input name="rut" class="form-control" required placeholder="Ej: 12345678-9">
        </div>
        <div class="mb-3">
            <label>Nombre</label>
            <input name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Contraseña</label>
            <input type="password" name="pass" class="form-control" required>
        </div>
        <button class="btn btn-primary">Registrarme</button>
    </form>
    
    <div class="mt-3">
        <p>¿Ya tienes cuenta? <a href="login_cliente.php">Inicia sesión aquí</a></p>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>