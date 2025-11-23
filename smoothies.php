<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['usuario']) && ($_SESSION['usuario']['perfilTipo'] ?? '') === 'Empleado') {
    header('Location: admin.php');
    exit;
}

$cliente = $_SESSION['cliente'] ?? null;

$productos = [];

$query = 'query($cat: String!) { getProductosByCategoria(categoria: $cat) { id nombre precio stock categoria descripcion imagen } }';
$res = graphql_request($query, ['cat' => 'smoothies']);
if (empty($res['errors'])) {
    $productos = $res['data']['getProductosByCategoria'] ?? [];
}

if (empty($productos)) {
    $q2 = 'query { getProductos { id nombre precio stock categoria descripcion imagen } }';
    $r2 = graphql_request($q2, [], false);
    if (empty($r2['errors'])) {
        $todos = $r2['data']['getProductos'] ?? [];
        $productos = array_values(array_filter($todos, function($p) {
            return isset($p['categoria']) && strtolower($p['categoria']) === 'smoothies';
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
    .placeholder-smoothies {
        background: linear-gradient(135deg, #4ecdc4, #45b7d1);
    }
</style>

<div class="container mt-5">
    <h2>Smoothies</h2>
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
                        <img src="<?= htmlspecialchars($producto['imagen']) ?>" 
                             class="card-img-top" 
                             style="height:220px; object-fit:cover;"
                             alt="<?= htmlspecialchars($producto['nombre']) ?>"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1570197788417-0e82375c9371?w=400'">
                    <?php else: ?>
                        <div class="image-placeholder placeholder-smoothies">
                            Smoothies
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                        <?= $stockBadge ?>
                        <p class="mt-2 text-success fw-bold">$<?= number_format($producto['precio'], 0, ',', '.') ?></p>

                        <?php if ($producto['stock'] > 0): ?>
                            <form method="post" action="<?= $cliente ? 'carrito.php' : 'login_cliente.php' ?>">
                                <?php if ($cliente): ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="productoId" value="<?= htmlspecialchars($producto['id']) ?>">
                                <?php endif; ?>
                                <div class="input-group mb-2">
                                    <input type="number" name="cantidad" value="1" min="1" max="<?= $maxCompra ?>" 
                                           class="form-control" style="max-width:80px;"
                                           onchange="validarStock(this, <?= $maxCompra ?>)">
                                    <button class="btn btn-primary" type="submit">Agregar al carrito</button>
                                </div>
                                <small class="text-muted">Máximo: <?= $maxCompra ?> unidades</small>
                            </form>
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