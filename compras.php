<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

include 'header.php';

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
    validate_csrf();
    
    $compraId = sanitizar_input($_POST['compraId'] ?? '');
    $motivo = sanitizar_input($_POST['motivo'] ?? '');
    
    if ($compraId && $motivo !== '') {
        $m = 'mutation($cid:String!, $motivo:String!) { solicitarReembolso(compraId:$cid, motivo:$motivo) { id compraId motivo estado } }';
        $r = graphql_request($m, ['cid'=>$compraId,'motivo'=>$motivo], false);
        if (!empty($r['errors'])) $error = $r['errors'][0]['message'] ?? 'Error solicitando reembolso';
        else $mensaje = 'Reembolso solicitado correctamente';
    } else $error = 'Debe indicar un motivo';
}

$q = 'query($rut:String!) { 
    getCompraByCliente(rut:$rut){ 
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
    } 
}';
$r = graphql_request($q, ['rut'=>$clienteRut], false);
$compras = $r['data']['getCompraByCliente'] ?? [];

$qp = 'query { getProductos { id nombre precio } }';
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

include 'navbar.php';
?>

<div class="container mt-5">
    <h2>Mis compras</h2>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($compras)): ?>
        <div class="alert alert-info">No has realizado compras aún.</div>
    <?php else: ?>
        <?php foreach ($compras as $compra): 
            $fechaFormateada = formatearFecha($compra['fecha']);
            $tieneDescuento = isset($compra['descuentoAplicado']) && $compra['descuentoAplicado'] > 0;
            $totalMostrar = $compra['totalPagado'] ?? $compra['total'] ?? 0;
            $descuento = $compra['descuentoAplicado'] ?? 0;
            $cuponUsado = $compra['cuponUsado'] ?? null;
            
            $itemsJSON = htmlspecialchars(json_encode($compra['items']));
            $prodMapJSON = htmlspecialchars(json_encode($prodMap));
        ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title">Pedido: <?= htmlspecialchars($compra['id']) ?></h5>
                            <p class="card-text">
                                <small class="text-muted"><?= htmlspecialchars($fechaFormateada) ?></small>
                            </p>
                            
                            <?php if ($tieneDescuento && $cuponUsado): ?>
                                <p class="mb-1">
                                    <span class="badge bg-success">Cupón: <?= htmlspecialchars($cuponUsado) ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <h5 class="text-primary">$<?= number_format($totalMostrar, 0, ',', '.') ?></h5>
                            <?php if ($tieneDescuento): ?>
                                <p class="text-success mb-0">
                                    <small>Ahorro: $<?= number_format($descuento, 0, ',', '.') ?></small>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Productos comprados:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                    <tr>
                                        <td><?= htmlspecialchars($nombreProducto) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill"><?= $item['cantidad'] ?> und.</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-between flex-wrap gap-2">
                        <div>
                            <button class="btn btn-outline-success btn-sm descargar-ticket-btn" 
                                    data-compra-id="<?= htmlspecialchars($compra['id']) ?>" 
                                    data-fecha="<?= htmlspecialchars($fechaFormateada) ?>"
                                    data-total="<?= $compra['total'] ?? 0 ?>"
                                    data-descuento="<?= $descuento ?>"
                                    data-total-pagado="<?= $totalMostrar ?>"
                                    data-cupon="<?= htmlspecialchars($cuponUsado ?? '') ?>"
                                    data-items='<?= $itemsJSON ?>'
                                    data-prodmap='<?= $prodMapJSON ?>'>
                                Descargar boleta
                            </button>
                        </div>
                        
                        <div>
                            <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#reemb-<?= htmlspecialchars($compra['id']) ?>">
                                Solicitar reembolso
                            </button>
                        </div>
                    </div>

                    <div id="reemb-<?= htmlspecialchars($compra['id']) ?>" class="collapse mt-2">
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="reembolso" value="1">
                            <input type="hidden" name="compraId" value="<?= htmlspecialchars($compra['id']) ?>">
                            <div class="mb-2">
                                <textarea name="motivo" class="form-control" placeholder="Explique el motivo de su solicitud de reembolso" required rows="3" minlength="10"></textarea>
                            </div>
                            <button class="btn btn-danger" type="submit">Enviar solicitud</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function descargarTicket(compraId, fecha, total, descuento, totalPagado, cuponUsado, items, prodMap) {
    if (typeof items === 'string') {
        items = JSON.parse(items);
    }
    if (typeof prodMap === 'string') {
        prodMap = JSON.parse(prodMap);
    }
    
    const ticketDiv = document.createElement('div');
    ticketDiv.style.width = '400px';
    ticketDiv.style.padding = '20px';
    ticketDiv.style.backgroundColor = '#ffffff';
    ticketDiv.style.fontFamily = "'Courier New', monospace, 'Arial', sans-serif";
    ticketDiv.style.border = '1px solid #000';
    ticketDiv.style.boxSizing = 'border-box';
    ticketDiv.style.color = '#000000';
    
    let itemsHTML = '';
    let subtotalCalculado = 0;
    
    items.forEach(item => {
        const producto = prodMap[item.productoId] || { 
            nombre: 'Producto no disponible', 
            precio: 0 
        };
        const cantidad = parseInt(item.cantidad);
        const precio = parseInt(producto.precio) || 0;
        const subtotalItem = precio * cantidad;
        subtotalCalculado += subtotalItem;
        
        itemsHTML += `
            <div style="padding: 5px 0; border-bottom: 1px dashed #ccc;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-weight: bold;">${producto.nombre}</span>
                    <span>${cantidad} x $${precio.toLocaleString()}</span>
                </div>
                <div style="display: flex; justify-content: flex-end; font-size: 0.9em; color: #666;">
                    Subtotal: $${subtotalItem.toLocaleString()}
                </div>
            </div>
        `;
    });
    
    ticketDiv.innerHTML = `
        <div style="text-align: center; border-bottom: 2px dashed #000; margin-bottom: 15px; padding-bottom: 10px;">
            <h3 style="margin: 0; font-weight: bold; color: #000;">Natural Power</h3>
            <p style="margin: 5px 0; font-size: 0.9em; color: #000;">Jugos y Smoothies Naturales</p>
            <p style="margin: 0; font-size: 0.8em; color: #666;">Ticket de Compra</p>
        </div>
        
        <div style="margin-bottom: 15px; color: #000;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <strong>Pedido #:</strong>
                <span>${compraId}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <strong>Fecha:</strong>
                <span>${fecha}</span>
            </div>
            ${cuponUsado ? `
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <strong>Cupón:</strong>
                <span style="background-color: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.9em;">${cuponUsado}</span>
            </div>
            ` : ''}
        </div>
        
        <hr style="border-top: 1px dashed #ccc; margin: 10px 0;">
        
        <div style="margin-bottom: 15px; color: #000;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9em; color: #666;">
                <span>Producto</span>
                <span>Cant. x Precio</span>
            </div>
            ${itemsHTML}
        </div>
        
        <hr style="border-top: 1px dashed #ccc; margin: 10px 0;">
        
        <div style="font-weight: bold; margin-top: 15px; padding-top: 10px; border-top: 2px dashed #000; color: #000;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span>Subtotal:</span>
                <span>$${subtotalCalculado.toLocaleString()}</span>
            </div>
            
            ${descuento > 0 ? `
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px; color: #28a745;">
                <span>Descuento:</span>
                <span>-$${descuento.toLocaleString()}</span>
            </div>
            ` : ''}
            
            <div style="display: flex; justify-content: space-between; font-size: 1.2em; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ccc;">
                <strong>TOTAL A PAGAR:</strong>
                <strong>$${totalPagado.toLocaleString()}</strong>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc; color: #000;">
            <p style="margin: 0; font-size: 0.9em; color: #666;">¡Gracias por su compra!</p>
            <p style="margin: 0; font-size: 0.9em; color: #666;">Vuelva pronto</p>
        </div>
    `;
    
    ticketDiv.style.position = 'fixed';
    ticketDiv.style.left = '-9999px';
    ticketDiv.style.top = '0';
    document.body.appendChild(ticketDiv);
    
    const loadingAlert = document.createElement('div');
    loadingAlert.className = 'alert alert-info position-fixed top-0 start-50 translate-middle-x mt-3';
    loadingAlert.style.zIndex = '9999';
    loadingAlert.innerHTML = 'Generando ticket, por favor espere...';
    document.body.appendChild(loadingAlert);
    
    html2canvas(ticketDiv, {
        scale: 2,
        backgroundColor: '#ffffff',
        useCORS: true,
        logging: false,
        allowTaint: true
    }).then(canvas => {
        const link = document.createElement('a');
        const fechaDescarga = new Date().toISOString().split('T')[0];
        link.download = `ticket-${compraId}-${fechaDescarga}.png`;
        link.href = canvas.toDataURL('image/png');
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        document.body.removeChild(ticketDiv);
        document.body.removeChild(loadingAlert);
        
        const successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3';
        successAlert.style.zIndex = '9999';
        successAlert.innerHTML = '✓ Ticket descargado exitosamente';
        document.body.appendChild(successAlert);
        
        setTimeout(() => {
            if (successAlert.parentNode) {
                document.body.removeChild(successAlert);
            }
        }, 3000);
        
    }).catch(error => {
        console.error('Error al generar imagen del ticket:', error);
        
        if (loadingAlert.parentNode) {
            document.body.removeChild(loadingAlert);
        }
        
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-danger position-fixed top-0 start-50 translate-middle-x mt-3';
        errorAlert.style.zIndex = '9999';
        errorAlert.innerHTML = 'Error al descargar el ticket. Intente nuevamente.';
        document.body.appendChild(errorAlert);
        
        setTimeout(() => {
            if (errorAlert.parentNode) {
                document.body.removeChild(errorAlert);
            }
        }, 5000);
        
        if (ticketDiv.parentNode) {
            document.body.removeChild(ticketDiv);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.descargar-ticket-btn').forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '⌛ Generando...';
            this.disabled = true;
            
            descargarTicket(
                this.getAttribute('data-compra-id'),
                this.getAttribute('data-fecha'),
                parseFloat(this.getAttribute('data-total')),
                parseFloat(this.getAttribute('data-descuento')),
                parseFloat(this.getAttribute('data-total-pagado')),
                this.getAttribute('data-cupon'),
                this.getAttribute('data-items'),
                this.getAttribute('data-prodmap')
            );
            
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 2000);
        });
    });
});
</script>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>