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

$mensaje = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reembolso'])) {
    $compraId = $_POST['compraId'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    if ($compraId && $motivo !== '') {
        $m = 'mutation($cid:String!, $motivo:String!) { solicitarReembolso(compraId:$cid, motivo:$motivo) { id compraId motivo estado } }';
        $r = graphql_request($m, ['cid'=>$compraId,'motivo'=>$motivo], false);
        if (!empty($r['errors'])) $error = $r['errors'][0]['message'] ?? 'Error solicitando reembolso';
        else $mensaje = 'Reembolso solicitado correctamente';
    } else $error = 'Debe indicar un motivo';
}

$q = 'query($rut:String!) { getCompraByCliente(rut:$rut){ id clienteId total fecha items { productoId cantidad } } }';
$r = graphql_request($q, ['rut'=>$clienteRut], false);
$compras = $r['data']['getCompraByCliente'] ?? [];

$qp = 'query { getProductos { id nombre } }';
$rp = graphql_request($qp, [], false);
$productos = $rp['data']['getProductos'] ?? [];
$prodMap = [];
foreach ($productos as $p) $prodMap[$p['id']] = $p;

function formatearFecha($fecha) {
    if (is_numeric($fecha)) {
        if ($fecha > 1000000000000) {
            $fecha = $fecha / 1000;
        }
        $date = new DateTime();
        $date->setTimestamp($fecha);
        return $date->format('Y-m-d H:i:s');
    } else {
        try {
            $date = new DateTime($fecha);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return $fecha;
        }
    }
}

include 'header.php';
include 'navbar.php';
?>
<div class="container mt-5">
    <h2>Mis compras</h2>
    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (empty($compras)): ?>
        <div class="alert alert-info">No has realizado compras aún.</div>
    <?php else: ?>
        <?php foreach ($compras as $compra): 
            $fechaFormateada = formatearFecha($compra['fecha']);
        ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>Pedido: <?= htmlspecialchars($compra['id']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($fechaFormateada) ?></small>
                        </div>
                        <div class="text-end">
                            <strong>Total: $<?= number_format($compra['total'] ?? 0, 0, ',', '.') ?></strong>
                        </div>
                    </div>
                    <hr>
                    <h6>Productos comprados:</h6>
                    <ul class="list-group">
                        <?php 
                        $itemsAgrupados = [];
                        foreach ($compra['items'] as $it) {
                            $productoId = $it['productoId'];
                            if (!isset($itemsAgrupados[$productoId])) {
                                $itemsAgrupados[$productoId] = [
                                    'producto' => $prodMap[$productoId] ?? null,
                                    'cantidad' => 0
                                ];
                            }
                            $itemsAgrupados[$productoId]['cantidad'] += intval($it['cantidad']);
                        }
                        
                        foreach ($itemsAgrupados as $item): 
                            $nombreProducto = $item['producto']['nombre'] ?? 'Producto no disponible';
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($nombreProducto) ?>
                                <span class="badge bg-primary rounded-pill"><?= $item['cantidad'] ?> und.</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="mt-3">
                        <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#reemb-<?= htmlspecialchars($compra['id']) ?>">
                            Solicitar reembolso
                        </button>
                        <div id="reemb-<?= htmlspecialchars($compra['id']) ?>" class="collapse mt-2">
                            <form method="post">
                                <input type="hidden" name="reembolso" value="1">
                                <input type="hidden" name="compraId" value="<?= htmlspecialchars($compra['id']) ?>">
                                <div class="mb-2">
                                    <textarea name="motivo" class="form-control" placeholder="Explique el motivo de su solicitud de reembolso" required rows="3"></textarea>
                                </div>
                                <button class="btn btn-danger" type="submit">Enviar solicitud</button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>