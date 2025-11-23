<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$q = 'query { getComprasDelDia { id clienteId total fecha items { productoId cantidad } } }';
$r = graphql_request($q, [], true);
$compras = $r['data']['getComprasDelDia'] ?? [];

$qprod = 'query { getProductos { id nombre precio } }';
$rp = graphql_request($qprod, [], true);
$productos = $rp['data']['getProductos'] ?? [];
$map = [];
foreach ($productos as $p) $map[$p['id']] = $p;

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
<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .ticket-print, .ticket-print * {
            visibility: visible;
        }
        .ticket-print {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .no-print {
            display: none !important;
        }
        .card {
            border: 1px solid #000 !important;
            box-shadow: none !important;
        }
    }
    .ticket-print {
        border: 2px dashed #ddd;
        padding: 15px;
        margin-bottom: 20px;
    }
</style>

<div class="container mt-5">
    <h2>Ticket de pedidos del día</h2>
    <?php if (empty($compras)): ?>
        <div class="alert alert-info">No hay pedidos para el día de hoy.</div>
    <?php else: ?>
        <?php foreach ($compras as $c): 
            $fechaFormateada = formatearFecha($c['fecha']);
        ?>
            <div class="card mb-3 p-3 ticket-print" id="ticket-<?= htmlspecialchars($c['id']) ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="text-center mb-3">
                            <h4 class="mb-0">Natural Power</h4>
                            <small class="text-muted">Jugos y Smoothies Naturales</small>
                        </div>
                        
                        <div class="row mb-2">
                            <div class="col-6">
                                <strong>Pedido:</strong> <?= htmlspecialchars($c['id']) ?>
                            </div>
                            <div class="col-6 text-end">
                                <strong>Fecha:</strong> <?= htmlspecialchars($fechaFormateada) ?>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <strong>Cliente:</strong> <?= htmlspecialchars($c['clienteId']) ?>
                        </div>

                        <hr>
                        
                        <div class="mb-3">
                            <strong>Productos:</strong>
                            <table class="table table-sm table-borderless mb-0">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cant</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_pedido = 0;
                                    foreach ($c['items'] as $it): 
                                        $prod = $map[$it['productoId']] ?? null;
                                        $nombre = $prod ? $prod['nombre'] : $it['productoId'];
                                        $precio = $prod ? intval($prod['precio']) : 0;
                                        $cantidad = intval($it['cantidad']);
                                        $subtotal = $precio * $cantidad;
                                        $total_pedido += $subtotal;
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($nombre) ?></td>
                                            <td class="text-center"><?= $cantidad ?></td>
                                            <td class="text-end">$<?= number_format($precio, 0, ',', '.') ?></td>
                                            <td class="text-end">$<?= number_format($subtotal, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <hr>
                        
                        <div class="row">
                            <div class="col-8">
                                <strong>TOTAL:</strong>
                            </div>
                            <div class="col-4 text-end">
                                <strong>$<?= number_format($total_pedido, 0, ',', '.') ?></strong>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <small class="text-muted">¡Gracias por su compra!</small>
                        </div>
                    </div>
                </div>

                <div class="mt-3 no-print">
                    <button class="btn btn-outline-primary btn-sm" onclick="imprimirTicket('<?= htmlspecialchars($c['id']) ?>')">
                        🖨️ Imprimir ticket
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="no-print">
        <a href="admin.php" class="btn btn-secondary">Volver</a>
    </div>
</div>

<script>
function imprimirTicket(ticketId) {
    const todosTickets = document.querySelectorAll('.ticket-print');
    todosTickets.forEach(ticket => {
        ticket.style.display = 'none';
    });

    const ticketEspecifico = document.getElementById('ticket-' + ticketId);
    if (ticketEspecifico) {
        ticketEspecifico.style.display = 'block';
        
        window.print();
        
        setTimeout(() => {
            todosTickets.forEach(ticket => {
                ticket.style.display = 'block';
            });
        }, 100);
    }
}

window.addEventListener('beforeprint', (event) => {
    const ticketsVisibles = document.querySelectorAll('.ticket-print[style*="display: block"]');
    if (ticketsVisibles.length === 0) {
        document.body.classList.add('printing-all');
    }
});

window.addEventListener('afterprint', (event) => {
    document.body.classList.remove('printing-all');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>