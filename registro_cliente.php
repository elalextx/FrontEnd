<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

include 'header.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    
    $rut = validar_rut(sanitizar_input($_POST['rut'] ?? ''));
    $nombre = sanitizar_input($_POST['nombre'] ?? '');
    $email = validar_email($_POST['email'] ?? '');
    $pass = sanitizar_input($_POST['pass'] ?? '');
 
    if (!$rut || !$nombre || !$email || !$pass) {
        $error = 'Todos los campos son obligatorios';
    } elseif (strlen($nombre) < 2) {
        $error = 'El nombre debe tener al menos 2 caracteres';
    } elseif (strlen($pass) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } else {
        $q_check = 'query($rut: String!, $email: String!) { 
            getClientes { rut email } 
        }';
        $r_check = graphql_request($q_check, [], false);
        
        $clienteExistente = false;
        if (!empty($r_check['data']['getClientes'])) {
            foreach ($r_check['data']['getClientes'] as $c) {
                if ($c['rut'] === $rut) {
                    $error = 'El RUT ya está registrado';
                    $clienteExistente = true;
                    break;
                }
                if ($c['email'] === $email) {
                    $error = 'El email ya está registrado';
                    $clienteExistente = true;
                    break;
                }
            }
        }
        
        if (!$clienteExistente) {
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
            } elseif (isset($r['data']['addCliente'])) {
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
}

include 'navbar.php';
?>
<div class="container mt-5">
    <h2>Registro de Cliente</h2>
    <?php if ($mensaje): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php echo csrf_field(); ?>
        <div class="mb-3">
            <label class="form-label">RUT</label>
            <input name="rut" class="form-control" required 
                   placeholder="Ej: 12345678-9"
                   pattern="[0-9]{7,8}-[0-9kK]{1}"
                   title="Formato: 12345678-9">
        </div>
        <div class="mb-3">
            <label class="form-label">Nombre completo</label>
            <input name="nombre" class="form-control" required 
                   minlength="2"
                   maxlength="100">
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="pass" class="form-control" required
                   minlength="6"
                   pattern=".{6,}"
                   title="Mínimo 6 caracteres">
            <small class="text-muted">Mínimo 6 caracteres</small>
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