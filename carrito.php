<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['usuario']) && ($_SESSION['usuario']['perfilTipo'] ?? '') === 'Empleado') {
    header('Location: admin.php');
    exit;
}

if (!isset($_SESSION['cliente'])) {
    header('Location: login_cliente.php');
    exit;
}

$cliente = $_SESSION['cliente'];
$clienteRut = $cliente['rut'];
$clienteNombre = $cliente['nombre'];

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $productoId = $_POST['productoId'] ?? '';
        $cantidad = intval($_POST['cantidad'] ?? 1);

        if ($productoId && $cantidad > 0) {
            $qStock = 'query($id: ID!) { getProducto(id: $id) { id nombre stock } }';
            $rStock = graphql_request($qStock, ['id' => $productoId], false);
            
            if (!empty($rStock['errors'])) {
                $error = 'Error verificando stock del producto';
            } else {
                $producto = $rStock['data']['getProducto'] ?? null;
                if (!$producto) {
                    $error = 'Producto no encontrado';
                } elseif ($producto['stock'] < $cantidad) {
                    $error = "Stock insuficiente. Disponible: {$producto['stock']} unidades";
                } else {
                    $mAdd = 'mutation($cid:String!, $pid:String!, $cant:Int!) { 
                        agregarItemCarrito(clienteId:$cid, productoId:$pid, cantidad:$cant){ 
                            id 
                            clienteId 
                            items { 
                                productoId 
                                cantidad 
                            } 
                            total 
                        } 
                    }';
                    $rAdd = graphql_request($mAdd, [
                        'cid' => $clienteRut,
                        'pid' => $productoId,
                        'cant' => $cantidad
                    ], false);
                    
                    if (!empty($rAdd['errors'])) {
                        $error = $rAdd['errors'][0]['message'] ?? 'Error agregando al carrito';
                    } else {
                        $mensaje = 'Producto agregado al carrito';
                    }
                }
            }
        } else {
            $error = 'Datos del producto inválidos';
        }
    } elseif ($action === 'confirmar') {
        $mConf = 'mutation($cid:String!) { 
            confirmarCompra(clienteId:$cid){ 
                id 
                clienteId 
                total 
                fecha 
                items { 
                    productoId 
                    cantidad 
                } 
            } 
        }';
        $rConf = graphql_request($mConf, ['cid'=>$clienteRut], false);
        if (!empty($rConf['errors'])) {
            $error = $rConf['errors'][0]['message'] ?? 'Error confirmando compra';
        } else {
            $mensaje = 'Compra realizada con éxito. Puedes ver tus pedidos en Mis Compras.';
        }
    }
}

$q = 'query($cid:String!) { 
    getCarritoByCliente(clienteId:$cid){ 
        id 
        clienteId 
        items { 
            productoId 
            cantidad 
        } 
        total 
    } 
}';
$r = graphql_request($q, ['cid'=>$clienteRut], false);
$carrito = $r['data']['getCarritoByCliente'] ?? null;

$qp = 'query { getProductos { id nombre precio imagen stock } }';
$rp = graphql_request($qp, [], false);
$productos = $rp['data']['getProductos'] ?? [];
$prodMap = [];
foreach ($productos as $p) $prodMap[$p['id']] = $p;

$itemsAgrupados = [];
if ($carrito && !empty($carrito['items'])) {
    foreach ($carrito['items'] as $item) {
        $productoId = $item['productoId'];
        if (!isset($itemsAgrupados[$productoId])) {
            $itemsAgrupados[$productoId] = [
                'producto' => $prodMap[$productoId] ?? null,
                'cantidad' => 0
            ];
        }
        $itemsAgrupados[$productoId]['cantidad'] += intval($item['cantidad']);
    }
}

include 'header.php';
include 'navbar.php';
?>
<div class="container mt-5">
    <h2>Carrito de <?= htmlspecialchars($clienteNombre) ?></h2>
    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (empty($itemsAgrupados)): ?>
        <div class="alert alert-info">Tu carrito está vacío.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio unit.</th>
                    <th>Subtotal</th>
                    <th>Stock disponible</th>
                </tr>
            </thead>
            <tbody>
                <?php $suma = 0;
                foreach ($itemsAgrupados as $productoId => $itemData):
                    $p = $itemData['producto'];
                    $cantidad = $itemData['cantidad'];
                    $precio = $p ? intval($p['precio']) : 0;
                    $sub = $precio * $cantidad;
                    $suma += $sub;
                    $stockDisponible = $p ? intval($p['stock']) : 0;
                    $stockSuficiente = $stockDisponible >= $cantidad;
                    $filaClass = $stockSuficiente ? '' : 'table-warning';
                ?>
                    <tr class="<?= $filaClass ?>">
                        <td>
                            <?php if ($p && isset($p['imagen'])): ?>
                                <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" style="width: 50px; height: 50px; object-fit: cover;" class="me-2">
                            <?php endif; ?>
                            <?= htmlspecialchars($p['nombre'] ?? 'Producto no disponible') ?>
                            <?php if (!$stockSuficiente): ?>
                                <span class="badge bg-danger ms-2">Stock insuficiente</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $cantidad ?></td>
                        <td>$<?= number_format($precio,0,',','.') ?></td>
                        <td>$<?= number_format($sub,0,',','.') ?></td>
                        <td>
                            <?php if ($stockDisponible > 5): ?>
                                <span class="badge bg-success"><?= $stockDisponible ?> disponibles</span>
                            <?php elseif ($stockDisponible > 0): ?>
                                <span class="badge bg-warning text-dark"><?= $stockDisponible ?> disponibles</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Agotado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                    <td><strong>$<?= number_format($suma,0,',','.') ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <div>
                <a href="jugos.php" class="btn btn-outline-primary me-2">Seguir comprando - Jugos</a>
                <a href="smoothies.php" class="btn btn-outline-primary">Seguir comprando - Smoothies</a>
            </div>

            <?php
            $todoEnStock = true;
            foreach ($itemsAgrupados as $itemData) {
                $p = $itemData['producto'];
                $cantidad = $itemData['cantidad'];
                $stockDisponible = $p ? intval($p['stock']) : 0;
                if ($stockDisponible < $cantidad) {
                    $todoEnStock = false;
                    break;
                }
            }
            ?>

            <?php if ($todoEnStock): ?>
                <form method="post" onsubmit="return confirm('¿Confirmar compra?');">
                    <input type="hidden" name="action" value="confirmar">
                    <button class="btn btn-success btn-lg" type="submit">Confirmar compra</button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Stock insuficiente:</strong> Ajusta las cantidades o elimina productos sin stock para continuar.
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($carrito && empty($carrito['items'])): ?>
        <div class="text-center mt-4">
            <a href="jugos.php" class="btn btn-primary me-2">Ir a Jugos</a>
            <a href="smoothies.php" class="btn btn-primary">Ir a Smoothies</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>