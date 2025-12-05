<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isEmpleado = isset($_SESSION['usuario']) && ($_SESSION['usuario']['perfilTipo'] ?? '') === 'Empleado';

if ($isEmpleado) {
    header('Location: admin.php');
    exit;
}

$cliente = $_SESSION['cliente'] ?? null;

$productos = [];

$query = 'query($cat: String!) { getProductosByCategoria(categoria: $cat) { id nombre precio stock categoria descripcion imagen } }';
$res = graphql_request($query, ['cat' => 'jugos']);
if (empty($res['errors'])) {
    $productos = $res['data']['getProductosByCategoria'] ?? [];
}

if (empty($productos)) {
    $q2 = 'query { getProductos { id nombre precio stock categoria descripcion imagen } }';
    $r2 = graphql_request($q2, [], false);
    if (empty($r2['errors'])) {
        $todos = $r2['data']['getProductos'] ?? [];
        $productos = array_values(array_filter($todos, function($p) {
            return isset($p['categoria']) && strtolower($p['categoria']) === 'jugos';
        }));
    }
}

include 'header.php';
include 'navbar.php';
?>

<style>
    .image-placeholder {
        height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
        color: white;
        border-radius: 0.375rem 0.375rem 0 0;
    }
    .placeholder-jugos {
        background: linear-gradient(135deg, #ff6b6b, #ffa726);
    }
</style>

<div class="container mt-5">
    <h2>Jugos naturales</h2>
    <div class="row mt-4">
        <?php if (empty($productos)): ?>
            <div class="alert alert-warning">No hay productos en esta categoría.</div>
        <?php endif; ?>

        <?php foreach ($productos as $producto): 
            if ($producto['stock'] > 5) {
                $stockBadge = '<span class="badge bg-success">Disponible</span>';
                $maxCompra = $producto['stock'];
            } elseif ($producto['stock'] > 0) {
                $stockBadge = '<span class="badge bg-warning text-dark">Últimas unidades</span>';
                $maxCompra = $producto['stock'];
            } else {
                $stockBadge = '<span class="badge bg-danger">Agotado</span>';
                $maxCompra = 0;
            }
            
            $categoria = strtolower($producto['categoria'] ?? '');
            $tiene_imagen = !empty($producto['imagen']);
        ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <?php if ($tiene_imagen): ?>
                        <img src="<?= htmlspecialchars($producto['imagen'], ENT_QUOTES, 'UTF-8') ?>" 
                             class="card-img-top" 
                             style="height:220px; object-fit:cover;"
                             alt="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=400'">
                    <?php else: ?>
                        <div class="image-placeholder placeholder-jugos">
                            Jugos
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <?= $stockBadge ?>
                        <p class="mt-2 text-success fw-bold">$<?= number_format($producto['precio'], 0, ',', '.') ?></p>

                        <?php if ($producto['stock'] > 0): ?>
                            <?php if ($isEmpleado): ?>
                                <div class="input-group mb-2">
                                    <input type="number" value="1" min="1" max="<?= $maxCompra ?>" 
                                           class="form-control" style="max-width:80px;" disabled>
                                    <button class="btn btn-secondary" disabled>No disponible</button>
                                </div>
                                <small class="text-muted">Máximo: <?= $maxCompra ?> unidades</small>
                            <?php else: ?>
                                <form method="post" action="<?= $cliente ? 'carrito.php' : 'login_cliente.php' ?>">
                                    <?php if ($cliente): ?>
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="productoId" value="<?= htmlspecialchars($producto['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php else: ?>
                                        <input type="hidden" name="redirect_product_id" value="<?= htmlspecialchars($producto['id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="redirect_cantidad" id="cantidad_<?= htmlspecialchars($producto['id'], ENT_QUOTES, 'UTF-8') ?>" value="1">
                                    <?php endif; ?>
                                    <div class="input-group mb-2">
                                        <input type="number" name="cantidad" value="1" min="1" max="<?= $maxCompra ?>" 
                                               class="form-control" style="max-width:80px;"
                                               onchange="validarStock(this, <?= $maxCompra ?>)"
                                               <?php if (!$cliente): ?>id="input_cantidad_<?= htmlspecialchars($producto['id'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
                                        <button class="btn btn-primary" type="submit">Agregar al carrito</button>
                                    </div>
                                    <small class="text-muted">Máximo: <?= $maxCompra ?> unidades</small>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Agotado</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
function validarStock(input, stockDisponible) {
    const cantidad = parseInt(input.value);
    if (cantidad > stockDisponible) {
        alert(`Stock insuficiente. Máximo disponible: ${stockDisponible} unidades`);
        input.value = stockDisponible;
        return false;
    }
    if (cantidad < 1) {
        input.value = 1;
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[method="post"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const cantidadInput = this.querySelector('input[name="cantidad"]');
            const productoIdInput = this.querySelector('input[name="productoId"]');
            const redirectCantidadInput = this.querySelector('input[name="redirect_cantidad"]');
            
            if (!productoIdInput && redirectCantidadInput && cantidadInput) {
                const productoId = cantidadInput.id.replace('input_cantidad_', '');
                redirectCantidadInput.value = cantidadInput.value;
                const cantidad = cantidadInput.value;
                window.location.href = `login_cliente.php?product_id=${productoId}&cantidad=${cantidad}`;
                e.preventDefault();
                return false;
            }
            
            if (cantidadInput && productoIdInput) {
                const cantidad = parseInt(cantidadInput.value);
                const stockMax = parseInt(cantidadInput.max);
                
                if (cantidad > stockMax) {
                    e.preventDefault();
                    alert(`No puedes agregar más de ${stockMax} unidades de este producto.`);
                    return false;
                }
            }
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>