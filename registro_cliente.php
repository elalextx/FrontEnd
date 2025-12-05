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
    $direccion = trim($_POST['direccion'] ?? '');
    $comuna = trim($_POST['comuna'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if (!$rut || !$nombre || !$email || !$pass) {
        $error = 'RUT, Nombre, Email y Contraseña son obligatorios';
    } else {
        $m = 'mutation(
            $rut:String!, 
            $nombre:String!, 
            $email:String!, 
            $pass:String!,
            $direccion:String,
            $comuna:String,
            $provincia:String,
            $region:String,
            $fechaNacimiento:String,
            $sexo:String,
            $telefono:String
        ) { 
            addCliente(
                rut:$rut, 
                nombre:$nombre, 
                email:$email, 
                pass:$pass,
                direccion:$direccion,
                comuna:$comuna,
                provincia:$provincia,
                region:$region,
                fechaNacimiento:$fechaNacimiento,
                sexo:$sexo,
                telefono:$telefono
            ) { 
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
            'pass' => $pass,
            'direccion' => $direccion,
            'comuna' => $comuna,
            'provincia' => $provincia,
            'region' => $region,
            'fechaNacimiento' => $fechaNacimiento,
            'sexo' => $sexo,
            'telefono' => $telefono
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

    <form method="post" id="registroForm">
        <div class="row">
            <div class="col-md-6">
                <h4>Datos Personales</h4>
                <div class="mb-3">
                    <label class="form-label">RUT *</label>
                    <input name="rut" class="form-control" required placeholder="Ej: 12345678-9" pattern="[0-9]{7,8}-[0-9kK]{1}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nombre Completo *</label>
                    <input name="nombre" class="form-control" required minlength="2">
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha de Nacimiento</label>
                    <input type="date" name="fecha_nacimiento" class="form-control" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Sexo</label>
                    <select name="sexo" class="form-control">
                        <option value="">Seleccione...</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                        <option value="Otro">Otro</option>
                        <option value="Prefiero no decir">Prefiero no decir</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <h4>Contacto</h4>
                <div class="mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" name="telefono" class="form-control" placeholder="+56 9 1234 5678">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña *</label>
                    <input type="password" name="pass" class="form-control" required minlength="6">
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h4>Dirección</h4>
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label">Dirección</label>
                <input name="direccion" class="form-control" placeholder="Calle, número, departamento">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Comuna</label>
                <input name="comuna" class="form-control" placeholder="Ej: Providencia">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Provincia</label>
                <input name="provincia" class="form-control" placeholder="Ej: Santiago">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Región</label>
                <input name="region" class="form-control" placeholder="Ej: Metropolitana">
            </div>
        </div>
        
        <div class="mt-3">
            <button class="btn btn-primary" type="submit">Registrarme</button>
        </div>
    </form>
    
    <div class="mt-3">
        <p>¿Ya tienes cuenta? <a href="login_cliente.php">Inicia sesión aquí</a></p>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
document.getElementById('registroForm').addEventListener('submit', function(e) {
    const fechaNacimiento = document.querySelector('input[name="fecha_nacimiento"]');
    if (fechaNacimiento.value) {
        const fechaNac = new Date(fechaNacimiento.value);
        const hoy = new Date();
        const edad = hoy.getFullYear() - fechaNac.getFullYear();
        const mes = hoy.getMonth() - fechaNac.getMonth();
        
        if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) {
            edad--;
        }
        
        if (edad < 18) {
            e.preventDefault();
            alert('Debes ser mayor de 18 años para registrarte');
            return false;
        }
    }
    return true;
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>