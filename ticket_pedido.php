<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$q = 'query { 
    getComprasDelDia { 
        id 
        clienteId 
        total 
        totalPagado 
        descuentoAplicado 
        cuponUsado 
        fecha 
        items { 
            productoId 
            cantidad 
        }
        clienteData {
            nombre
            email
            direccion
            comuna
            provincia
            region
            telefono
        }
    } 
}';

$r = graphql_request($q, [], true);
$compras = $r['data']['getComprasDelDia'] ?? [];

if (!is_array($compras)) {
    $compras = [];
}

$qprod = 'query { getProductos { id nombre precio } }';
$rp = graphql_request($qprod, [], true);
$productos = $rp['data']['getProductos'] ?? [];
$map = [];
foreach ($productos as $p) {
    if (isset($p['id'])) {
        $map[$p['id']] = $p;
    }
}

function formatearFecha($fecha) {
    if (empty($fecha)) {
        return 'Fecha no disponible';
    }
    
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
        .text-success {
            color: #000 !important;
        }
    }
    .ticket-print {
        border: 2px dashed #ddd;
        padding: 15px;
        margin-bottom: 20px;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
    .ticket-header {
        text-align: center;
        padding-bottom: 10px;
        border-bottom: 2px dashed #000;
        margin-bottom: 15px;
    }
    .ticket-item {
        padding: 5px 0;
        border-bottom: 1px dashed #ccc;
    }
    .ticket-total {
        font-weight: bold;
        font-size: 1.2em;
        margin-top: 15px;
        padding-top: 10px;
        border-top: 2px dashed #000;
    }
    .ticket-descuento {
        color: #198754;
        font-weight: bold;
    }
</style>

<div class="container mt-5">
    <h2>Ticket de pedidos del día</h2>
    
    <?php if (empty($compras)): ?>
        <div class="alert alert-info">No hay pedidos para el día de hoy.</div>
    <?php else: ?>
        <div class="alert alert-success mb-4">
            <strong><?= count($compras) ?> pedido(s) para hoy</strong>
        </div>
        
        <?php foreach ($compras as $c): 
            if (!$c || !isset($c['id'])) {
                continue;
            }
            
            $fechaFormateada = isset($c['fecha']) ? formatearFecha($c['fecha']) : 'Fecha no disponible';
            $tieneDescuento = isset($c['descuentoAplicado']) && $c['descuentoAplicado'] > 0;
            $totalPedido = $c['total'] ?? 0;
            $descuento = $c['descuentoAplicado'] ?? 0;
            $totalPagado = $c['totalPagado'] ?? $totalPedido;
            $cuponUsado = $c['cuponUsado'] ?? null;
            $clienteData = $c['clienteData'] ?? null;
            $items = $c['items'] ?? [];
        ?>
            <div class="card mb-4 p-3 ticket-print" id="ticket-<?= htmlspecialchars($c['id']) ?>">
                <div class="ticket-header">
                    <h4 class="mb-1 fw-bold">Natural Power</h4>
                    <p class="mb-1 text-muted small">Jugos y Smoothies Naturales</p>
                </div>
                
                <div class="mb-3">
                    <div class="row mb-1">
                        <div class="col-6">
                            <strong>Pedido #:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?= htmlspecialchars($c['id'] ?? 'N/A') ?>
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-6">
                            <strong>Fecha:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?= htmlspecialchars($fechaFormateada) ?>
                        </div>
                    </div>
                    
                    <?php if ($clienteData && isset($clienteData['nombre'])): ?>
                    <div class="card mt-2 mb-3">
                        <div class="card-body p-3 bg-light">
                            <h6 class="card-title mb-2">Datos del Cliente y Despacho:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Cliente:</strong><br>
                                    <?= htmlspecialchars($clienteData['nombre'] ?? 'N/A') ?></p>
                                    <p class="mb-1"><strong>Email:</strong><br>
                                    <?= htmlspecialchars($clienteData['email'] ?? 'N/A') ?></p>
                                    <p class="mb-1"><strong>Teléfono:</strong><br>
                                    <?= htmlspecialchars($clienteData['telefono'] ?? 'N/A') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Dirección:</strong><br>
                                    <?= htmlspecialchars($clienteData['direccion'] ?? 'N/A') ?></p>
                                    <p class="mb-1">
                                        <?= htmlspecialchars($clienteData['comuna'] ?? '') ?>
                                        <?= isset($clienteData['provincia']) && $clienteData['provincia'] ? ', ' . htmlspecialchars($clienteData['provincia']) : '' ?>
                                        <?= isset($clienteData['region']) && $clienteData['region'] ? ', ' . htmlspecialchars($clienteData['region']) : '' ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($tieneDescuento && $cuponUsado): ?>
                    <div class="row mb-1">
                        <div class="col-6">
                            <strong>Cupón:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <span class="badge bg-success"><?= htmlspecialchars($cuponUsado) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <hr class="my-2">
                
                <div class="mb-3">
                    <?php if (!empty($items)): ?>
                    <table class="table table-sm table-borderless mb-0">
                        <thead>
                            <tr>
                                <th class="text-start">Producto</th>
                                <th class="text-center">Cant</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_calculado = 0;
                            foreach ($items as $it): 
                                $prod = isset($it['productoId']) ? ($map[$it['productoId']] ?? null) : null;
                                $nombre = $prod ? $prod['nombre'] : ($it['productoId'] ?? 'Producto no disponible');
                                $precio = $prod ? intval($prod['precio']) : 0;
                                $cantidad = isset($it['cantidad']) ? intval($it['cantidad']) : 0;
                                $subtotal = $precio * $cantidad;
                                $total_calculado += $subtotal;
                            ?>
                                <tr class="ticket-item">
                                    <td class="text-start"><?= htmlspecialchars($nombre) ?></td>
                                    <td class="text-center"><?= $cantidad ?></td>
                                    <td class="text-end">$<?= number_format($precio, 0, ',', '.') ?></td>
                                    <td class="text-end">$<?= number_format($subtotal, 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="text-muted">No hay productos en este pedido.</p>
                    <?php endif; ?>
                </div>

                <hr class="my-2">
                
                <div class="ticket-total">
                    <div class="row mb-2">
                        <div class="col-8">
                            <strong>Subtotal:</strong>
                        </div>
                        <div class="col-4 text-end">
                            <strong>$<?= number_format($totalPedido, 0, ',', '.') ?></strong>
                        </div>
                    </div>
                    
                    <?php if ($tieneDescuento): ?>
                    <div class="row mb-2 ticket-descuento">
                        <div class="col-8">
                            <strong>Descuento:</strong>
                        </div>
                        <div class="col-4 text-end">
                            <strong>-$<?= number_format($descuento, 0, ',', '.') ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-8">
                            <strong>TOTAL:</strong>
                        </div>
                        <div class="col-4 text-end">
                            <strong class="fs-5">$<?= number_format($totalPagado, 0, ',', '.') ?></strong>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4 pt-3 border-top">
                    <p class="mb-0 small text-muted">¡Gracias por su compra!</p>
                    <p class="mb-0 small text-muted">Datos de despacho enviados al email registrado</p>
                </div>

                <div class="mt-3 no-print text-center">
                    <button class="btn btn-outline-primary btn-sm" onclick="imprimirTicket('<?= htmlspecialchars($c['id']) ?>')">
                        🖨️ Imprimir ticket
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="no-print text-center mt-4">
        <a href="admin.php" class="btn btn-secondary">Volver al panel</a>
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
        
        const printContent = ticketEspecifico.innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = printContent;
        window.print();
        
        document.body.innerHTML = originalContent;
        
        setTimeout(() => {
            todosTickets.forEach(ticket => {
                ticket.style.display = 'block';
            });
        }, 100);
    }
}

function imprimirTodosTickets() {
    const printWindow = window.open('', '_blank');
    const tickets = document.querySelectorAll('.ticket-print');
    
    let printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Tickets del día - Natural Power</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .ticket { 
                    border: 1px solid #000; 
                    padding: 15px; 
                    margin-bottom: 20px; 
                    max-width: 400px; 
                    page-break-inside: avoid;
                }
                .ticket-header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 15px; }
                .ticket-total { font-weight: bold; margin-top: 15px; padding-top: 10px; border-top: 2px dashed #000; }
                @media print {
                    .ticket { margin: 0; }
                }
            </style>
        </head>
        <body>
            <h2 style="text-align: center; margin-bottom: 30px;">Tickets del día - Natural Power</h2>
    `;
    
    tickets.forEach(ticket => {
        printContent += ticket.innerHTML;
    });
    
    printContent += `
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.print();
}

window.addEventListener('beforeprint', (event) => {
    document.body.classList.add('printing');
});

window.addEventListener('afterprint', (event) => {
    document.body.classList.remove('printing');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>