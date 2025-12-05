<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

include 'header.php';

$isEmpleado = isset($_SESSION['usuario']) && ($_SESSION['usuario']['perfilTipo'] ?? '') === 'Empleado';
$cliente = $_SESSION['cliente'] ?? null;

$res = graphql_request('query { getProductos { id nombre precio stock categoria descripcion imagen } }', [], false);
$todos_productos = $res['data']['getProductos'] ?? [];

$productos_destacados = array_filter($todos_productos, function($producto) {
    $categorias_destacadas = ['jugos', 'smoothies'];
    return $producto['stock'] > 0 && 
           in_array(strtolower($producto['categoria'] ?? ''), $categorias_destacadas);
});

$productos_destacados = array_slice($productos_destacados, 0, 6);

include 'navbar.php';
?>
<style>
    .carousel-item img {
        height: 500px;
        object-fit: cover;
        border-radius: 2vw;
        cursor: pointer;
        transition: transform 0.3s ease;
    }
    .carousel-item img:hover {
        transform: scale(1.02);
    }
    .carousel-caption {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 1rem;
    }
    .carousel-caption h3, .carousel-caption p {
        color: black;
    }
    .carousel-control-prev, .carousel-control-next {
        width: 5%;
    }
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
    .placeholder-smoothies {
        background: linear-gradient(135deg, #4ecdc4, #45b7d1);
    }
</style>

<div class="container-fluid mt-3">
    <div id="demo" class="carousel slide carousel-fade" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#demo" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#demo" data-bs-slide-to="1"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=1200" 
                     alt="Jugos naturales" 
                     class="d-block w-100"
                     onclick="window.location.href='jugos.php'"/>
                <div class="carousel-caption">
                    <h3>Jugos naturales</h3>
                    <p>Refresca tu día con el sabor puro de frutas recién exprimidas.</p>
                    <button class="btn btn-success mt-2" onclick="window.location.href='jugos.php'">
                        Ver Jugos
                    </button>
                </div>
            </div>
            <div class="carousel-item">
                <img src="https://images.unsplash.com/photo-1622597467836-f3285f2131b8?w=1200" 
                     alt="Smoothies" 
                     class="d-block w-100"
                     onclick="window.location.href='smoothies.php'"/>
                <div class="carousel-caption">
                    <h3>Smoothies</h3>
                    <p>Cremosos, nutritivos y llenos de energía, preparados con frutas frescas.</p>
                    <button class="btn btn-success mt-2" onclick="window.location.href='smoothies.php'">
                        Ver Smoothies
                    </button>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#demo" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#demo" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>
</div>

<div class="container mt-5">
    <h2>Productos destacados</h2>
    <?php if (empty($productos_destacados)): ?>
        <div class="alert alert-info">
            No hay productos destacados disponibles en este momento.
        </div>
    <?php else: ?>
        <div class="row mt-4">
            <?php 
            foreach ($productos_destacados as $producto): 
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
                                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=400'">
                        <?php else: ?>
                            <div class="image-placeholder placeholder-<?= $categoria ?>">
                                <?= ucfirst($categoria) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                            <span class="badge bg-info"><?= htmlspecialchars($producto['categoria']) ?></span>
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
                                            <input type="hidden" name="productoId" value="<?= htmlspecialchars($producto['id']) ?>">
                                        <?php else: ?>
                                            <input type="hidden" name="redirect_product_id" value="<?= htmlspecialchars($producto['id']) ?>">
                                            <input type="hidden" name="redirect_cantidad" id="cantidad_<?= htmlspecialchars($producto['id']) ?>" value="1">
                                        <?php endif; ?>
                                        <div class="input-group mb-2">
                                            <input type="number" name="cantidad" value="1" min="1" max="<?= $maxCompra ?>" 
                                                   class="form-control" style="max-width:80px;"
                                                   onchange="validarStock(this, <?= $maxCompra ?>)"
                                                   <?php if (!$cliente): ?>id="input_cantidad_<?= htmlspecialchars($producto['id']) ?>"<?php endif; ?>>
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
    <?php endif; ?>
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