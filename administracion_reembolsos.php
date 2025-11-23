<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$mensaje = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'atender') {
    $id = $_POST['id'] ?? '';
    $estado = $_POST['estado'] ?? '';
    if ($id && $estado) {
        $m = 'mutation($id:ID!, $estado:String!) { atenderReembolso(id:$id, estado:$estado) { id compraId motivo estado } }';
        $r = graphql_request($m, ['id'=>$id,'estado'=>$estado], true);
        if (!empty($r['errors'])) $error = $r['errors'][0]['message'] ?? 'Error atendiendo reembolso';
        else $mensaje = 'Reembolso actualizado';
    } else $error = 'Datos inválidos';
}

$q = 'query { getReembolsos { id compraId motivo estado } }';
$res = graphql_request($q, [], true);
$reembolsos = $res['data']['getReembolsos'] ?? [];

include 'header.php';
include 'navbar.php';
?>
<div class="container mt-5">
    <h2>Administración de Reembolsos</h2>

    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (empty($reembolsos)): ?>
        <div class="alert alert-info">No hay solicitudes de reembolso.</div>
    <?php else: ?>
        <?php foreach ($reembolsos as $r): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <p><strong>Pedido:</strong> <?= htmlspecialchars($r['compraId']) ?></p>
                    <p><strong>Motivo:</strong> <?= htmlspecialchars($r['motivo']) ?></p>
                    <p><strong>Estado:</strong> <?= htmlspecialchars($r['estado']) ?></p>

                    <form method="post" class="d-flex gap-2">
                        <input type="hidden" name="accion" value="atender">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($r['id']) ?>">
                        <select name="estado" class="form-select" style="max-width:200px;">
                            <option value="Aprobado">Aprobado</option>
                            <option value="Rechazado">Rechazado</option>
                        </select>
                        <button class="btn btn-primary">Actualizar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="admin.php" class="btn btn-secondary">Volver</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
