<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['cliente'])) {
    header('Location: index.php');
    exit;
}

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$clientes = graphql_request('query { getClientes { id rut nombre email estado } }', [], true);
$empleados = graphql_request('query { getEmpleados { id rut nombre email cargo } }', [], true);
$productos = graphql_request('query { getProductos { id nombre precio stock categoria } }', [], true);
$reemb = graphql_request('query { getReembolsos { id compraId motivo estado } }', [], true);
$cupones = graphql_request('query { getCupones { id codigo porcentaje tipo activo } }', [], true);
$comprasHoy = graphql_request('query { getComprasDelDia { id clienteId total fecha items { productoId cantidad } } }', [], true);

$countClientes = count($clientes['data']['getClientes'] ?? []);
$countEmpleados = count($empleados['data']['getEmpleados'] ?? []);
$countProductos = count($productos['data']['getProductos'] ?? []);
$countReemb = count($reemb['data']['getReembolsos'] ?? []);
$countCupones = count($cupones['data']['getCupones'] ?? []);
$countComprasHoy = count($comprasHoy['data']['getComprasDelDia'] ?? []);

include 'header.php';
include 'navbar.php';
?>

<div class="container mt-5">
    <h2>Panel Administrativo</h2>
    <p>Bienvenido, <?= htmlspecialchars($usuario['nombre'] ?? 'Empleado') ?></p>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card p-3">
                <h5>Clientes</h5>
                <p class="display-6"><?= $countClientes ?></p>
                <a href="administracion_cliente.php" class="btn btn-sm btn-primary">Gestionar clientes</a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <h5>Empleados</h5>
                <p class="display-6"><?= $countEmpleados ?></p>
                <a href="administracion_empleado.php" class="btn btn-sm btn-primary">Gestionar empleados</a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <h5>Productos</h5>
                <p class="display-6"><?= $countProductos ?></p>
                <a href="administracion_productos.php" class="btn btn-sm btn-primary">Gestionar productos</a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <h5>Reembolsos</h5>
                <p class="display-6"><?= $countReemb ?></p>
                <a href="administracion_reembolsos.php" class="btn btn-sm btn-primary">Atender reembolsos</a>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3">
                <h5>Cupones</h5>
                <p class="display-6"><?= $countCupones ?></p>
                <a href="administracion_cupones.php" class="btn btn-sm btn-primary">Gestionar cupones</a>
            </div>
        </div>        

    </div>

    <hr class="my-4">

    <div class="mb-3">
        <h5>Pedidos del día</h5>
        <p>Pedidos a preparar hoy: <strong><?= $countComprasHoy ?></strong></p>
        <a href="ticket_pedido.php" class="btn btn-outline-secondary">Ver ticket de pedidos del día</a>
    </div>

    <div class="mt-4">
        <a href="generacion_reporte.php" class="btn btn-success">Generar reporte de ventas</a>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>