<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$mensaje = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = intval($_POST['precio'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $categoria = trim($_POST['categoria'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $imagen = trim($_POST['imagen'] ?? '');

    if ($nombre && $precio >= 0 && $categoria) {
        $m = 'mutation($nombre:String!, $precio:Int!, $stock:Int!, $categoria:String!, $descripcion:String, $imagen:String) { addProducto(nombre:$nombre, precio:$precio, stock:$stock, categoria:$categoria, descripcion:$descripcion, imagen:$imagen) { id nombre } }';
        $r = graphql_request($m, ['nombre'=>$nombre,'precio'=>$precio,'stock'=>$stock,'categoria'=>$categoria,'descripcion'=>$descripcion,'imagen'=>$imagen], true);
        if (!empty($r['errors'])) $error = $r['errors'][0]['message'] ?? 'Error añadiendo producto';
        else $mensaje = 'Producto agregado';
    } else $error = 'Nombre, precio y categoría son obligatorios';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = intval($_POST['precio'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $categoria = trim($_POST['categoria'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $imagen = trim($_POST['imagen'] ?? '');

    if ($id && $nombre && $categoria) {
        $m = 'mutation($id:ID!, $nombre:String!, $precio:Int!, $stock:Int!, $categoria:String!, $descripcion:String, $imagen:String) { updateProducto(id:$id, nombre:$nombre, precio:$precio, stock:$stock, categoria:$categoria, descripcion:$descripcion, imagen:$imagen) { id nombre } }';
        $r = graphql_request($m, ['id'=>$id,'nombre'=>$nombre,'precio'=>$precio,'stock'=>$stock,'categoria'=>$categoria,'descripcion'=>$descripcion,'imagen'=>$imagen], true);
        if (!empty($r['errors'])) $error = $r['errors'][0]['message'] ?? 'Error editando producto';
        else $mensaje = 'Producto actualizado';
    } else $error = 'Nombre y categoría son obligatorios';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? '';
    if ($id) {
        $m = 'mutation($id:ID!) { deleteProducto(id:$id) { status message } }';
        $r = graphql_request($m, ['id'=>$id], true);
        if (!empty($r['errors'])) $error = $r['errors'][0]['message'] ?? 'Error eliminando producto';
        else $mensaje = 'Producto eliminado';
    } else $error = 'ID inválido';
}

$q = 'query { getProductos { id nombre precio stock categoria descripcion imagen } }';
$res = graphql_request($q, [], true);
$productos = $res['data']['getProductos'] ?? [];

include 'header.php';
include 'navbar.php';
?>
<div class="container mt-5">
    <h2>Administración de Productos</h2>

    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card p-3 mb-4">
        <h5>Agregar producto</h5>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="row g-2">
                <div class="col-md-3">
                    <input class="form-control" name="nombre" placeholder="Nombre" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" name="precio" placeholder="Precio" required min="0">
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" name="stock" placeholder="Stock" required min="0">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="categoria" required>
                        <option value="">Seleccione categoría</option>
                        <option value="jugos">Jugos</option>
                        <option value="smoothies">Smoothies</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input class="form-control" name="imagen" placeholder="URL imagen (opcional)">
                </div>
            </div>
            <div class="mt-2">
                <input class="form-control" name="descripcion" placeholder="Descripción (opcional)">
            </div>
            <div class="mt-2">
                <button class="btn btn-primary">Agregar</button>
            </div>
        </form>
    </div>

    <h5>Lista de productos</h5>
    <?php if (empty($productos)): ?>
        <div class="alert alert-info">No hay productos.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Categoria</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                        <td>$<?= number_format($p['precio'], 0, ',', '.') ?></td>
                        <td><?= intval($p['stock']) ?></td>
                        <td>
                            <span class="badge bg-info"><?= htmlspecialchars($p['categoria']) ?></span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-<?= htmlspecialchars($p['id']) ?>">Editar</button>

                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                                <button class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar producto?');">Eliminar</button>
                            </form>

                            <div class="collapse mt-2" id="edit-<?= htmlspecialchars($p['id']) ?>">
                                <form method="post" class="row g-2">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                                    <div class="col-md-3">
                                        <input class="form-control" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control" name="precio" value="<?= intval($p['precio']) ?>" required min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" class="form-control" name="stock" value="<?= intval($p['stock']) ?>" required min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" name="categoria" required>
                                            <option value="jugos" <?= ($p['categoria'] ?? '') === 'jugos' ? 'selected' : '' ?>>Jugos</option>
                                            <option value="smoothies" <?= ($p['categoria'] ?? '') === 'smoothies' ? 'selected' : '' ?>>Smoothies</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input class="form-control" name="imagen" value="<?= htmlspecialchars($p['imagen'] ?? '') ?>" placeholder="URL imagen">
                                    </div>
                                    <div class="col-12 mt-2">
                                        <input class="form-control" name="descripcion" value="<?= htmlspecialchars($p['descripcion'] ?? '') ?>" placeholder="Descripción">
                                    </div>
                                    <div class="col-12 mt-2">
                                        <button class="btn btn-sm btn-success">Guardar cambios</button>
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="collapse" data-bs-target="#edit-<?= htmlspecialchars($p['id']) ?>">Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="admin.php" class="btn btn-secondary">Volver</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>