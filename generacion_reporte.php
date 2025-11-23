<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$mensaje = $error = '';
$resultado = null;

function timestampToDateTime($timestamp) {
    if (is_numeric($timestamp) && $timestamp > 0) {
        if ($timestamp > 1000000000000) {
            $timestamp = $timestamp / 1000;
        }
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        return $date;
    }
    return null;
}

if (isset($_POST['generar_csv'])) {
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $producto_nombre = trim($_POST['producto_nombre'] ?? '');
    $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
    
    if (!$start) {
        $error = 'Seleccione fecha de inicio para generar el CSV';
    } else {
        if (!$end) {
            $end = date('Y-m-d', strtotime($start . ' +14 days'));
        }
        
        $inicio = new DateTime($start . ' 00:00:00', new DateTimeZone('UTC'));
        $fin = new DateTime($end . ' 23:59:59', new DateTimeZone('UTC'));
        
        if ($inicio > $fin) {
            $error = 'La fecha de inicio no puede ser mayor a la fecha fin';
        } else {
            $q = 'query { getCompras { id clienteId total fecha items { productoId cantidad } } }';
            $r = graphql_request($q, [], true);
            
            if (!empty($r['errors'])) {
                $error = $r['errors'][0]['message'] ?? 'Error obteniendo compras';
            } else {
                $compras = $r['data']['getCompras'] ?? [];
                
                $q_productos = 'query { getProductos { id nombre precio } }'; 
                $r_productos = graphql_request($q_productos, [], true);
                $productos = $r_productos['data']['getProductos'] ?? [];
                $productos_map = [];
                foreach ($productos as $p) {
                    $productos_map[$p['id']] = $p;
                }
                
                $q_clientes = 'query { getClientes { rut nombre } }';
                $r_clientes = graphql_request($q_clientes, [], true);
                $clientes = $r_clientes['data']['getClientes'] ?? [];
                $clientes_map = [];
                foreach ($clientes as $c) {
                    $clientes_map[$c['rut']] = $c;
                }
                
                $filtradas = [];
                
                foreach ($compras as $c) {
                    $fecha_compra = null;
                    if (is_numeric($c['fecha'])) {
                        $fecha_compra = timestampToDateTime($c['fecha']);
                    } else {
                        try {
                            $fecha_compra = new DateTime($c['fecha']);
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                    
                    if (!$fecha_compra) continue;
                    
                    if ($fecha_compra < $inicio || $fecha_compra > $fin) {
                        continue;
                    }
                    
                    if ($cliente_nombre !== '') {
                        $cliente_info = $clientes_map[$c['clienteId']] ?? null;
                        if (!$cliente_info || stripos($cliente_info['nombre'], $cliente_nombre) === false) {
                            continue;
                        }
                    }
                    
                    if ($producto_nombre !== '') {
                        $tiene_producto = false;
                        foreach ($c['items'] as $item) {
                            $prod_info = $productos_map[$item['productoId']] ?? null;
                            if ($prod_info && stripos($prod_info['nombre'], $producto_nombre) !== false) {
                                $tiene_producto = true;
                                break;
                            }
                        }
                        if (!$tiene_producto) {
                            continue;
                        }
                    }
                    
                    $filtradas[] = [
                        'compra' => $c,
                        'fecha_datetime' => $fecha_compra
                    ];
                }
                
                if (empty($filtradas)) {
                    $error = 'No hay ventas que coincidan con los filtros aplicados para generar el CSV';
                } else {
                    $filename = 'reporte_ventas_' . date('Y-m-d_His') . '.csv';
                    
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    $output = fopen('php://output', 'w');
                    
                    fputs($output, "\xEF\xBB\xBF");
                    
                    fputcsv($output, ['Reporte de Ventas - Natural Power']);
                    fputcsv($output, ['Periodo:', $start . ' a ' . $end]);
                    fputcsv($output, ['Fecha generación:', date('Y-m-d H:i:s')]);
                    fputcsv($output, []); 
                    
                    fputcsv($output, ['ID Pedido', 'Cliente', 'Producto', 'Cantidad', 'Precio Unitario', 'Subtotal', 'Fecha', 'Total Pedido']);
                    
                    $total_general = 0;
                    
                    foreach ($filtradas as $item) {
                        $c = $item['compra'];
                        $fecha_compra = $item['fecha_datetime'];
                        
                        $cliente_info = $clientes_map[$c['clienteId']] ?? null;
                        $cliente_nombre_csv = $cliente_info ? $cliente_info['nombre'] : $c['clienteId'];
                        
                        foreach ($c['items'] as $item_carrito) {
                            $prod_info = $productos_map[$item_carrito['productoId']] ?? null;
                            $nombre_producto = $prod_info ? $prod_info['nombre'] : $item_carrito['productoId'];
                            
                            $precio_unitario = $prod_info && isset($prod_info['precio']) ? $prod_info['precio'] : 0;
                            $subtotal = $precio_unitario * intval($item_carrito['cantidad']);
                            
                            fputcsv($output, [
                                $c['id'],
                                $cliente_nombre_csv,
                                $nombre_producto,
                                intval($item_carrito['cantidad']),
                                '$' . number_format($precio_unitario, 0, ',', '.'),
                                '$' . number_format($subtotal, 0, ',', '.'),
                                $fecha_compra->format('Y-m-d H:i:s'),
                                '$' . number_format($c['total'], 0, ',', '.')
                            ]);
                        }
                        
                        $total_general += intval($c['total']);
                    }
                    
                    fputcsv($output, []);
                    fputcsv($output, ['TOTAL GENERAL:', '', '', '', '', '', '', '$' . number_format($total_general, 0, ',', '.')]);
                    
                    fclose($output);
                    exit; 
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ver_reporte'])) {
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $producto_nombre = trim($_POST['producto_nombre'] ?? '');
    $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
    
    if (!$start) {
        $error = 'Seleccione fecha de inicio';
    } else {
        if (!$end) {
            $end = date('Y-m-d', strtotime($start . ' +14 days'));
        }
        
        $inicio = new DateTime($start . ' 00:00:00', new DateTimeZone('UTC'));
        $fin = new DateTime($end . ' 23:59:59', new DateTimeZone('UTC'));
        
        if ($inicio > $fin) {
            $error = 'La fecha de inicio no puede ser mayor a la fecha fin';
        } else {
            $q = 'query { getCompras { id clienteId total fecha items { productoId cantidad } } }';
            $r = graphql_request($q, [], true);
            
            if (!empty($r['errors'])) {
                $error = $r['errors'][0]['message'] ?? 'Error obteniendo compras';
            } else {
                $compras = $r['data']['getCompras'] ?? [];
                
                $q_productos = 'query { getProductos { id nombre precio } }';
                $r_productos = graphql_request($q_productos, [], true);
                $productos = $r_productos['data']['getProductos'] ?? [];
                $productos_map = [];
                foreach ($productos as $p) {
                    $productos_map[$p['id']] = $p;
                }
                
                $q_clientes = 'query { getClientes { rut nombre } }';
                $r_clientes = graphql_request($q_clientes, [], true);
                $clientes = $r_clientes['data']['getClientes'] ?? [];
                $clientes_map = [];
                foreach ($clientes as $c) {
                    $clientes_map[$c['rut']] = $c;
                }
                
                $filtradas = [];
                $totalVentas = 0;
                
                foreach ($compras as $c) {
                    $fecha_compra = null;
                    if (is_numeric($c['fecha'])) {
                        $fecha_compra = timestampToDateTime($c['fecha']);
                    } else {
                        try {
                            $fecha_compra = new DateTime($c['fecha']);
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                    
                    if (!$fecha_compra) continue;
                    
                    if ($fecha_compra < $inicio || $fecha_compra > $fin) {
                        continue;
                    }
                    
                    if ($cliente_nombre !== '') {
                        $cliente_info = $clientes_map[$c['clienteId']] ?? null;
                        if (!$cliente_info || stripos($cliente_info['nombre'], $cliente_nombre) === false) {
                            continue;
                        }
                    }
                    
                    if ($producto_nombre !== '') {
                        $tiene_producto = false;
                        foreach ($c['items'] as $item) {
                            $prod_info = $productos_map[$item['productoId']] ?? null;
                            if ($prod_info && stripos($prod_info['nombre'], $producto_nombre) !== false) {
                                $tiene_producto = true;
                                break;
                            }
                        }
                        if (!$tiene_producto) {
                            continue;
                        }
                    }
                    
                    $filtradas[] = [
                        'compra' => $c,
                        'fecha_datetime' => $fecha_compra
                    ];
                    $totalVentas += intval($c['total'] ?? 0);
                }
                
                $resultado = [
                    'inicio' => $inicio->format('Y-m-d'),
                    'fin' => $fin->format('Y-m-d'),
                    'count' => count($filtradas),
                    'total' => $totalVentas,
                    'items' => $filtradas,
                    'productos_map' => $productos_map,
                    'clientes_map' => $clientes_map,
                    'filtros' => [
                        'producto_nombre' => $producto_nombre,
                        'cliente_nombre' => $cliente_nombre
                    ]
                ];
            }
        }
    }
}

include 'header.php';
include 'navbar.php';
?>
<div class="container mt-5">
    <h2>Generación de Reporte de Ventas</h2>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Fecha inicio</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Fecha fin (opcional)</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
            <small class="form-text text-muted">Si no se especifica, se usan 14 días desde la fecha inicio</small>
        </div>
        <div class="col-md-3">
            <label class="form-label">Filtrar por producto</label>
            <input type="text" name="producto_nombre" class="form-control" placeholder="Nombre del producto..." value="<?= htmlspecialchars($_POST['producto_nombre'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Filtrar por cliente</label>
            <input type="text" name="cliente_nombre" class="form-control" placeholder="Nombre del cliente..." value="<?= htmlspecialchars($_POST['cliente_nombre'] ?? '') ?>">
        </div>
        
        <div class="col-12">
            <button type="submit" name="ver_reporte" value="1" class="btn btn-primary">Ver Reporte</button>
            <button type="submit" name="generar_csv" value="1" class="btn btn-success">Generar CSV</button>
            <a href="generacion_reporte.php" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    <?php if ($resultado): ?>
        <div class="card p-3 mb-3">
            <h5>Reporte desde <?= htmlspecialchars($resultado['inicio']) ?> hasta <?= htmlspecialchars($resultado['fin']) ?></h5>
            <p>
                Ventas: <strong><?= $resultado['count'] ?></strong> | 
                Total recaudado: <strong>$<?= number_format($resultado['total'], 0, ',', '.') ?></strong>
                <?php if ($resultado['filtros']['producto_nombre']): ?>
                    | Producto: <strong><?= htmlspecialchars($resultado['filtros']['producto_nombre']) ?></strong>
                <?php endif; ?>
                <?php if ($resultado['filtros']['cliente_nombre']): ?>
                    | Cliente: <strong><?= htmlspecialchars($resultado['filtros']['cliente_nombre']) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <h6>Detalle de ventas</h6>
        <?php if (empty($resultado['items'])): ?>
            <div class="alert alert-info">No hay ventas que coincidan con los filtros aplicados.</div>
        <?php else: ?>
            <?php foreach ($resultado['items'] as $item): 
                $c = $item['compra'];
                $fecha_compra = $item['fecha_datetime'];
                $cliente_info = $resultado['clientes_map'][$c['clienteId']] ?? null;
                $cliente_nombre_display = $cliente_info ? $cliente_info['nombre'] : $c['clienteId'];
            ?>
                <div class="card mb-2">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>Pedido:</strong> <?= htmlspecialchars($c['id']) ?><br>
                                <strong>Cliente:</strong> <?= htmlspecialchars($cliente_nombre_display) ?><br>
                                <strong>Fecha:</strong> <?= $fecha_compra->format('Y-m-d H:i:s') ?>
                                
                                <div class="mt-2">
                                    <strong>Productos:</strong>
                                    <ul class="mb-0">
                                        <?php 
                                        $productos_agrupados = [];
                                        foreach ($c['items'] as $item_carrito) {
                                            $prod_info = $resultado['productos_map'][$item_carrito['productoId']] ?? null;
                                            $nombre_producto = $prod_info ? $prod_info['nombre'] : $item_carrito['productoId'];
                                            
                                            if (!isset($productos_agrupados[$nombre_producto])) {
                                                $productos_agrupados[$nombre_producto] = 0;
                                            }
                                            $productos_agrupados[$nombre_producto] += intval($item_carrito['cantidad']);
                                        }
                                        
                                        foreach ($productos_agrupados as $nombre => $cantidad): 
                                        ?>
                                            <li><?= htmlspecialchars($nombre) ?> (<?= $cantidad ?> und.)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="text-end">
                                <strong>Total:</strong><br>
                                <span class="fs-4">$<?= number_format($c['total'] ?? 0, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <a href="admin.php" class="btn btn-secondary">Volver</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>