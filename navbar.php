<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$usuario = $_SESSION['usuario'] ?? null;
$cliente = $_SESSION['cliente'] ?? null;

$isEmpleado = !empty($usuario) && ($usuario['perfilTipo'] ?? '') === 'Empleado';
$isCliente = !empty($cliente);
$displayName = $usuario['nombre'] ?? $cliente['nombre'] ?? null;

$cantidadCarrito = 0;
if ($isCliente && !$isEmpleado) {
    require_once 'graphql.php';
    $clienteRut = $cliente['rut'];
    $q = 'query($cid:String!) { 
        getCarritoByCliente(clienteId:$cid){ 
            items { 
                cantidad 
            } 
        } 
    }';
    $r = graphql_request($q, ['cid'=>$clienteRut], false);
    $carrito = $r['data']['getCarritoByCliente'] ?? null;
    
    if ($carrito && !empty($carrito['items'])) {
        foreach ($carrito['items'] as $item) {
            $cantidadCarrito += intval($item['cantidad']);
        }
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-success" href="index.php">Natural Power</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (!$isEmpleado): ?>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="jugos.php">Jugos naturales</a></li>
                <li class="nav-item"><a class="nav-link" href="smoothies.php">Smoothies</a></li>
            </ul>
            <?php endif; ?>

            <ul class="navbar-nav ms-auto">
                <?php if ($displayName): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="perfilDropdown" role="button" data-bs-toggle="dropdown">
                            <?= htmlspecialchars($displayName) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="perfilDropdown">
                            <?php if ($isEmpleado): ?>
                                <li><a class="dropdown-item" href="admin.php">Panel administrativo</a></li>
                                <li><a class="dropdown-item" href="logout.php">Cerrar sesión</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="compras.php">Mis compras</a></li>
                                <li><a class="dropdown-item" href="logout_cliente.php">Cerrar sesión</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <?php if (!$isEmpleado): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="carrito.php">
                            🛒 Carrito
                            <?php if ($cantidadCarrito > 0): ?>
                                <span class="carrito-badge"><?= $cantidadCarrito ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login_cliente.php">Inicia sesión</a></li>
                    <li class="nav-item"><a class="nav-link" href="registro_cliente.php">Regístrate</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>