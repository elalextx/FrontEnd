<?php
require_once 'graphql.php';
if (session_status() === PHP_SESSION_NONE) session_start();

include 'header.php';

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['perfilTipo'] ?? '') !== 'Empleado') {
    header('Location: login_empleado.php');
    exit;
}

$mensaje = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    validate_csrf();
    
    if ($_POST['accion'] === 'crear') {
        $codigo = strtoupper(sanitizar_input($_POST['codigo'] ?? ''));
        $porcentaje = intval($_POST['porcentaje'] ?? 0);
        $tipo = sanitizar_input($_POST['tipo'] ?? 'porcentaje');
        $fechaInicio = sanitizar_input($_POST['fecha_inicio'] ?? '');
        $fechaFin = sanitizar_input($_POST['fecha_fin'] ?? '');
        $usosMaximos = intval($_POST['usos_maximos'] ?? 1);
        $minimoCompra = intval($_POST['minimo_compra'] ?? 0);
        $descuentoFijo = intval($_POST['descuento_fijo'] ?? 0);
        
        if ($codigo && $fechaInicio && $fechaFin) {
            $m = 'mutation(
                $codigo:String!, $porcentaje:Int!, $tipo:String!, 
                $fechaInicio:String!, $fechaFin:String!, $usosMaximos:Int!,
                $minimoCompra:Int, $descuentoFijo:Int
            ) { 
                crearCupon(
                    codigo:$codigo, porcentaje:$porcentaje, tipo:$tipo,
                    fechaInicio:$fechaInicio, fechaFin:$fechaFin, usosMaximos:$usosMaximos,
                    minimoCompra:$minimoCompra, descuentoFijo:$descuentoFijo
                ) { 
                    id codigo porcentaje tipo 
                } 
            }';
            
            $r = graphql_request($m, [
                'codigo' => $codigo,
                'porcentaje' => $porcentaje,
                'tipo' => $tipo,
                'fechaInicio' => $fechaInicio . 'T00:00:00.000Z',
                'fechaFin' => $fechaFin . 'T23:59:59.999Z',
                'usosMaximos' => $usosMaximos,
                'minimoCompra' => $minimoCompra,
                'descuentoFijo' => $descuentoFijo
            ], true);
            
            if (!empty($r['errors'])) {
                $error = $r['errors'][0]['message'] ?? 'Error creando cupón';
            } else {
                $mensaje = 'Cupón creado correctamente';
            }
        } else {
            $error = 'Faltan campos obligatorios';
        }
    }
    
    if ($_POST['accion'] === 'eliminar' && isset($_POST['cupon_id'])) {
        $cuponId = sanitizar_input($_POST['cupon_id']);
        
        $m = 'mutation($id: ID!) {
            deleteCupon(id: $id) {
                status
                message
            }
        }';
        
        $r = graphql_request($m, ['id' => $cuponId], true);
        
        if (!empty($r['errors'])) {
            $error = $r['errors'][0]['message'] ?? 'Error eliminando cupón';
        } else {
            $mensaje = 'Cupón eliminado correctamente';
        }
    }
}

$q = 'query { getCupones { id codigo porcentaje tipo fechaInicio fechaFin usosMaximos usosActuales activo minimoCompra descuentoFijo } }';
$r = graphql_request($q, [], true);
$cupones = $r['data']['getCupones'] ?? [];

function formatearFechaSegura($fechaInput) {
    if (empty($fechaInput)) {
        return 'N/A';
    }
    
    if (is_numeric($fechaInput)) {
        $timestamp = ($fechaInput > 1000000000000) ? intval($fechaInput / 1000) : intval($fechaInput);
        try {
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            return $date->format('d/m/Y');
        } catch (Exception $e) {
            return 'Fecha inválida';
        }
    }
    
    if (is_string($fechaInput)) {
        try {
            $fechaInput = trim($fechaInput);
            if (strpos($fechaInput, 'T') !== false) {
                $parts = explode('T', $fechaInput);
                $date = new DateTime($parts[0]);
                return $date->format('d/m/Y');
            }
            $date = new DateTime($fechaInput);
            return $date->format('d/m/Y');
        } catch (Exception $e) {
            return substr($fechaInput, 0, 15);
        }
    }
    
    return 'Formato desconocido';
}

function getEstadoCupon($cupon) {
    if (!$cupon['activo']) {
        return ['clase' => 'bg-secondary', 'texto' => 'Inactivo'];
    }
    
    $hoy = new DateTime();
    
    try {
        if (is_numeric($cupon['fechaInicio'])) {
            $timestamp = ($cupon['fechaInicio'] > 1000000000000) 
                ? intval($cupon['fechaInicio'] / 1000) 
                : intval($cupon['fechaInicio']);
            $inicio = new DateTime();
            $inicio->setTimestamp($timestamp);
        } else {
            $inicio = new DateTime($cupon['fechaInicio']);
        }
        
        if (is_numeric($cupon['fechaFin'])) {
            $timestamp = ($cupon['fechaFin'] > 1000000000000) 
                ? intval($cupon['fechaFin'] / 1000) 
                : intval($cupon['fechaFin']);
            $fin = new DateTime();
            $fin->setTimestamp($timestamp);
        } else {
            $fin = new DateTime($cupon['fechaFin']);
        }
        
        if ($hoy < $inicio) {
            return ['clase' => 'bg-info', 'texto' => 'Próximo'];
        }
        
        if ($hoy > $fin) {
            return ['clase' => 'bg-danger', 'texto' => 'Expirado'];
        }
        
        if ($cupon['usosActuales'] >= $cupon['usosMaximos']) {
            return ['clase' => 'bg-warning', 'texto' => 'Agotado'];
        }
        
        return ['clase' => 'bg-success', 'texto' => 'Activo'];
        
    } catch (Exception $e) {
        return ['clase' => 'bg-warning', 'texto' => 'Error'];
    }
}

include 'navbar.php';
?>

<div class="container mt-5">
    <h2>Gestión de Cupones de Descuento</h2>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4 p-3">
        <h5>Crear nuevo cupón</h5>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="crear">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Código *</label>
                    <input type="text" name="codigo" class="form-control" placeholder="EJ: VERANO10" required pattern="[A-Z0-9]{3,20}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo *</label>
                    <select name="tipo" class="form-control" required onchange="toggleCamposDescuento(this.value)">
                        <option value="porcentaje">Porcentaje</option>
                        <option value="fijo">Monto fijo</option>
                    </select>
                </div>
                <div class="col-md-3" id="campo-porcentaje">
                    <label class="form-label">Porcentaje (%) *</label>
                    <input type="number" name="porcentaje" class="form-control" value="10" min="1" max="100" required>
                </div>
                <div class="col-md-3" id="campo-fijo" style="display: none;">
                    <label class="form-label">Descuento fijo ($)</label>
                    <input type="number" name="descuento_fijo" class="form-control" min="0" value="1000">
                </div>
            </div>
            
            <div class="row g-2 mt-2">
                <div class="col-md-3">
                    <label class="form-label">Fecha inicio *</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha fin *</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Usos máximos</label>
                    <input type="number" name="usos_maximos" class="form-control" value="100" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mínimo compra ($)</label>
                    <input type="number" name="minimo_compra" class="form-control" value="0" min="0">
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Crear cupón</button>
            </div>
        </form>
    </div>

    <h5>Cupones existentes (<?= count($cupones) ?>)</h5>
    <?php if (empty($cupones)): ?>
        <div class="alert alert-info">No hay cupones creados.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Tipo</th>
                    <th>Descuento</th>
                    <th>Vigencia</th>
                    <th>Usos</th>
                    <th>Mínimo</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cupones as $cupon): 
                    $fechaInicio = formatearFechaSegura($cupon['fechaInicio']);
                    $fechaFin = formatearFechaSegura($cupon['fechaFin']);
                    
                    if ($cupon['tipo'] === 'porcentaje') {
                        $descuentoText = $cupon['porcentaje'] . '%';
                    } else {
                        $descuentoText = '$' . number_format($cupon['descuentoFijo'] ?? 0, 0, ',', '.');
                    }
                    
                    $estado = getEstadoCupon($cupon);
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($cupon['codigo']) ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-info">
                                <?= htmlspecialchars($cupon['tipo']) ?>
                            </span>
                        </td>
                        <td><?= $descuentoText ?></td>
                        <td>
                            <small>
                                <?= $fechaInicio ?> - <?= $fechaFin ?>
                            </small>
                        </td>
                        <td>
                            <?= $cupon['usosActuales'] ?> / <?= $cupon['usosMaximos'] ?>
                        </td>
                        <td>
                            $<?= number_format($cupon['minimoCompra'], 0, ',', '.') ?>
                        </td>
                        <td>
                            <span class="badge <?= $estado['clase'] ?>">
                                <?= $estado['texto'] ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar el cupón <?= htmlspecialchars($cupon['codigo']) ?>?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="cupon_id" value="<?= htmlspecialchars($cupon['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="mt-3">
        <a href="admin.php" class="btn btn-secondary">Volver</a>
    </div>
</div>

<script>
function toggleCamposDescuento(tipo) {
    const campoPorcentaje = document.getElementById('campo-porcentaje');
    const campoFijo = document.getElementById('campo-fijo');
    const inputPorcentaje = campoPorcentaje.querySelector('input[name="porcentaje"]');
    const inputFijo = campoFijo.querySelector('input[name="descuento_fijo"]');
    
    if (tipo === 'porcentaje') {
        campoPorcentaje.style.display = 'block';
        campoFijo.style.display = 'none';
        inputPorcentaje.required = true;
        inputFijo.required = false;
    } else {
        campoPorcentaje.style.display = 'none';
        campoFijo.style.display = 'block';
        inputPorcentaje.required = false;
        inputFijo.required = true;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const tipoSelect = document.querySelector('select[name="tipo"]');
    if (tipoSelect) {
        toggleCamposDescuento(tipoSelect.value);
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>