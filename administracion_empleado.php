<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

include 'header.php';

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$mensaje = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    validate_csrf();
    
    $rut = validar_rut($_POST['rut'] ?? '');
    $nombre = sanitizar_input($_POST['nombre'] ?? '');
    $email = validar_email($_POST['email'] ?? '');
    $pass = sanitizar_input($_POST['pass'] ?? '');
    $cargo = sanitizar_input($_POST['cargo'] ?? '');

    if ($rut && $nombre && $email && $pass && $cargo) {
        $m = 'mutation($rut:String!, $nombre:String!, $email:String!, $pass:String!, $cargo:String!) { addEmpleado(rut:$rut, nombre:$nombre, email:$email, pass:$pass, cargo:$cargo) { id rut nombre email cargo } }';
        $r = graphql_request($m, ['rut'=>$rut,'nombre'=>$nombre,'email'=>$email,'pass'=>$pass,'cargo'=>$cargo], true);
        if (!empty($r['errors'])) $error = $r['errors'][0]['message'] ?? 'Error creando empleado';
        else $mensaje = 'Empleado creado correctamente';
    } else {
        $error = 'Todos los campos son obligatorios';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    validate_csrf();
    
    $rut = validar_rut($_POST['rut'] ?? '');
    $nombre = sanitizar_input($_POST['nombre'] ?? '');
    $email = validar_email($_POST['email'] ?? '');
    $cargo = sanitizar_input($_POST['cargo'] ?? '');

    if ($rut && $nombre && $email && $cargo) {
        $m = 'mutation($rut:String!, $nombre:String!, $email:String!, $cargo:String!) { updateEmpleadoCompleto(rut:$rut, nombre:$nombre, email:$email, cargo:$cargo) { id rut nombre email cargo } }';
        $r = graphql_request($m, ['rut'=>$rut,'nombre'=>$nombre,'email'=>$email,'cargo'=>$cargo], true);
        if (!empty($r['errors'])) $error = $r['errors'][0]['message'] ?? 'Error editando empleado';
        else $mensaje = 'Empleado actualizado correctamente';
    } else {
        $error = 'Todos los campos son obligatorios';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    validate_csrf();
    
    $rut = validar_rut($_POST['rut'] ?? '');
    
    if ($rut) {
        $m = 'mutation($rut:String!) { 
            deleteEmpleado(rut:$rut) { 
                status message 
            } 
        }';
        
        $r = graphql_request($m, ['rut' => $rut], true);
        
        if (!empty($r['errors'])) {
            $error = $r['errors'][0]['message'] ?? 'Error eliminando empleado';
        } else {
            $mensaje = 'Empleado eliminado correctamente';
        }
    } else {
        $error = 'RUT inválido';
    }
}

$q = 'query { getEmpleados { id rut nombre email cargo } }';
$res = graphql_request($q, [], true);
$empleados = $res['data']['getEmpleados'] ?? [];

$term = sanitizar_input($_GET['q'] ?? '');
if ($term !== '') {
    $empleados = array_values(array_filter($empleados, function($e) use ($term) {
        $t = mb_strtolower($term);
        return mb_strpos(mb_strtolower($e['nombre']), $t) !== false ||
               mb_strpos(mb_strtolower($e['rut']), $t) !== false;
    }));
}

include 'navbar.php';
?>
<div class="container mt-5">
    <h2>Gestión de Empleados</h2>

    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card mb-4 p-3">
        <h5>Buscar empleado</h5>
        <form method="get" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control" placeholder="Buscar por RUT o nombre..." value="<?= htmlspecialchars($term) ?>">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="administracion_empleado.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="card mb-4 p-3">
        <h5>Crear nuevo empleado</h5>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="crear">
            <div class="row g-2">
                <div class="col-md-2"><input class="form-control" name="rut" placeholder="RUT (12345678-9)" required pattern="[0-9]{7,8}-[0-9kK]{1}"></div>
                <div class="col-md-3"><input class="form-control" name="nombre" placeholder="Nombre" required minlength="2"></div>
                <div class="col-md-3"><input type="email" class="form-control" name="email" placeholder="Email" required></div>
                <div class="col-md-2"><input class="form-control" name="cargo" placeholder="Cargo" required></div>
                <div class="col-md-2"><input type="password" class="form-control" name="pass" placeholder="Contraseña" required minlength="6"></div>
            </div>
            <div class="mt-2">
                <button class="btn btn-primary">Crear empleado</button>
            </div>
        </form>
    </div>

    <h5>Lista de empleados <?= $term ? '(Filtrados: ' . count($empleados) . ')' : '' ?></h5>
    <?php if (empty($empleados)): ?>
        <div class="alert alert-info">No hay empleados<?= $term ? ' que coincidan con la búsqueda' : '' ?>.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>RUT</th><th>Nombre</th><th>Email</th><th>Cargo</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($empleados as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['rut']) ?></td>
                        <td><?= htmlspecialchars($e['nombre']) ?></td>
                        <td><?= htmlspecialchars($e['email']) ?></td>
                        <td><?= htmlspecialchars($e['cargo']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#edit-<?= htmlspecialchars($e['rut']) ?>">
                                Editar
                            </button>
                            <form method="post" class="d-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="rut" value="<?= htmlspecialchars($e['rut']) ?>">
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="return confirm('¿Está seguro de eliminar al empleado <?= htmlspecialchars($e['nombre']) ?>? Esta acción no se puede deshacer.')">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                    <tr class="collapse" id="edit-<?= htmlspecialchars($e['rut']) ?>">
                        <td colspan="5" class="bg-light">
                            <div class="p-3">
                                <h6>Editar empleado: <?= htmlspecialchars($e['nombre']) ?></h6>
                                <form method="post" class="row g-2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="accion" value="editar">
                                    <input type="hidden" name="rut" value="<?= htmlspecialchars($e['rut']) ?>">
                                    <div class="col-md-3">
                                        <input class="form-control" name="nombre" value="<?= htmlspecialchars($e['nombre']) ?>" required minlength="2">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($e['email']) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input class="form-control" name="cargo" value="<?= htmlspecialchars($e['cargo']) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-success">Guardar cambios</button>
                                        <button type="button" class="btn btn-secondary" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#edit-<?= htmlspecialchars($e['rut']) ?>">
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="admin.php" class="btn btn-secondary">Volver</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>