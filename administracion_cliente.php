<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$mensaje = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_confirmacion'])) {
    $rut = $_POST['rut'] ?? '';
    $accion = $_POST['accion_confirmacion'] ?? '';
    
    if ($rut && $accion) {
        $nuevoEstado = ($accion === 'aceptar') ? 'activo' : 'rechazado';
        
        $m = 'mutation($rut:String!, $estado:String!) { 
            updateCliente(rut:$rut, estado:$estado) { 
                id rut nombre email estado 
            } 
        }';
        
        $r = graphql_request($m, ['rut'=>$rut, 'estado'=>$nuevoEstado], true);
        
        if (!empty($r['errors'])) {
            $error = $r['errors'][0]['message'] ?? 'Error actualizando cliente';
        } else {
            $mensaje = ($accion === 'aceptar') 
                ? 'Cliente aceptado correctamente' 
                : 'Cliente rechazado correctamente';
        }
    } else {
        $error = 'Datos inválidos para la confirmación';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $rut = trim($_POST['rut'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    if ($rut && $nombre && $email && $estado) {
        $m = 'mutation($rut:String!, $nombre:String!, $email:String!, $estado:String!) { 
            updateClienteCompleto(rut:$rut, nombre:$nombre, email:$email, estado:$estado) { 
                id rut nombre email estado 
            } 
        }';
        
        $r = graphql_request($m, [
            'rut' => $rut,
            'nombre' => $nombre,
            'email' => $email,
            'estado' => $estado
        ], true);
        
        if (!empty($r['errors'])) {
            $error = $r['errors'][0]['message'] ?? 'Error editando cliente';
        } else {
            $mensaje = 'Cliente actualizado correctamente';
        }
    } else {
        $error = 'Todos los campos son obligatorios';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $rut = trim($_POST['rut'] ?? '');
    
    if ($rut) {
        $m = 'mutation($rut:String!) { 
            deleteCliente(rut:$rut) { 
                status message 
            } 
        }';
        
        $r = graphql_request($m, ['rut' => $rut], true);
        
        if (!empty($r['errors'])) {
            $error = $r['errors'][0]['message'] ?? 'Error eliminando cliente';
        } else {
            $mensaje = 'Cliente eliminado correctamente';
        }
    } else {
        $error = 'RUT inválido';
    }
}

$q = 'query { getClientes { id rut nombre email estado } }';
$res = graphql_request($q, [], true);
$clientes = $res['data']['getClientes'] ?? [];

$term = trim($_GET['q'] ?? '');
if ($term !== '') {
    $clientes = array_values(array_filter($clientes, function($c) use ($term) {
        $t = mb_strtolower($term);
        return mb_strpos(mb_strtolower($c['nombre']), $t) !== false ||
               mb_strpos(mb_strtolower($c['rut']), $t) !== false;
    }));
}

$clientesPendientes = array_filter($clientes, function($c) {
    return ($c['estado'] ?? 'pendiente') === 'pendiente';
});

include 'header.php';
include 'navbar.php';
?>

<div class="container mt-5">
    <h2>Gestión de Clientes</h2>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4 p-3">
        <h5>Buscar cliente</h5>
        <form method="get" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control" placeholder="Buscar por RUT o nombre..." value="<?= htmlspecialchars($term) ?>">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="administracion_cliente.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    <?php if (!empty($clientesPendientes)): ?>
        <div class="card mb-4 p-3">
            <h5>Clientes Pendientes de Confirmación</h5>
            <?php foreach ($clientesPendientes as $c): ?>
                <div class="border rounded p-3 mb-2">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <strong><?= htmlspecialchars($c['nombre']) ?></strong><br>
                            <small class="text-muted">RUT: <?= htmlspecialchars($c['rut']) ?></small><br>
                            <small class="text-muted">Email: <?= htmlspecialchars($c['email']) ?></small>
                        </div>
                        <div class="col-md-4 text-end">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="accion_confirmacion" value="aceptar">
                                <input type="hidden" name="rut" value="<?= htmlspecialchars($c['rut']) ?>">
                                <button class="btn btn-success btn-sm">Aceptar</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="accion_confirmacion" value="rechazar">
                                <input type="hidden" name="rut" value="<?= htmlspecialchars($c['rut']) ?>">
                                <button class="btn btn-danger btn-sm">Rechazar</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h5>Lista de clientes <?= $term ? '(Filtrados: ' . count($clientes) . ')' : '' ?></h5>
    <?php if (empty($clientes)): ?>
        <div class="alert alert-info">No hay clientes<?= $term ? ' que coincidan con la búsqueda' : '' ?>.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>RUT</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): 
                    $badgeClass = [
                        'pendiente' => 'bg-warning',
                        'activo' => 'bg-success', 
                        'rechazado' => 'bg-danger'
                    ][$c['estado'] ?? 'pendiente'] ?? 'bg-secondary';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($c['rut']) ?></td>
                        <td><?= htmlspecialchars($c['nombre']) ?></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td>
                            <span class="badge <?= $badgeClass ?>">
                                <?= htmlspecialchars($c['estado'] ?? 'pendiente') ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#edit-<?= htmlspecialchars($c['rut']) ?>">
                                Editar
                            </button>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="rut" value="<?= htmlspecialchars($c['rut']) ?>">
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="return confirm('¿Está seguro de eliminar al cliente <?= htmlspecialchars($c['nombre']) ?>? Esta acción no se puede deshacer.')">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                    <tr class="collapse" id="edit-<?= htmlspecialchars($c['rut']) ?>">
                        <td colspan="5" class="bg-light">
                            <div class="p-3">
                                <h6>Editar cliente: <?= htmlspecialchars($c['nombre']) ?></h6>
                                <form method="post" class="row g-2">
                                    <input type="hidden" name="accion" value="editar">
                                    <input type="hidden" name="rut" value="<?= htmlspecialchars($c['rut']) ?>">
                                    <div class="col-md-3">
                                        <input class="form-control" name="nombre" value="<?= htmlspecialchars($c['nombre']) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input class="form-control" name="email" value="<?= htmlspecialchars($c['email']) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="estado" class="form-control" required>
                                            <option value="pendiente" <?= ($c['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                            <option value="activo" <?= ($c['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
                                            <option value="rechazado" <?= ($c['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-success">Guardar cambios</button>
                                        <button type="button" class="btn btn-secondary" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#edit-<?= htmlspecialchars($c['rut']) ?>">
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