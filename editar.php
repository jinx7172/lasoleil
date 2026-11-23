<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'includes/conexion.php';

// 1. Procesar Edición de datos del producto
if (isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion'] ?? ''); // Opcional
    $precio = (float)$_POST['precio'];
    $categoria = $_POST['categoria'];
    $stock = (int)$_POST['stock'];
    
    $actual = $conn->query("SELECT imagen FROM productos WHERE id = $id")->fetch_assoc();
    $imagen_nombre = $actual['imagen'];

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $directorio = __DIR__ . "/assets/img/";
        $nombre_archivo = preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['imagen']['name']));
        $imagen_nombre = time() . "_" . $nombre_archivo; 
        move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre);
        if ($actual['imagen'] != 'default.jpg' && file_exists($directorio . $actual['imagen'])) { 
            unlink($directorio . $actual['imagen']); 
        }
    }

    $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, categoria=?, stock=?, imagen=? WHERE id=?");
    $stmt->bind_param("ssdsisi", $nombre, $descripcion, $precio, $categoria, $stock, $imagen_nombre, $id);
    $stmt->execute();
    $mensaje = "✅ Producto '<strong>" . htmlspecialchars($nombre) . "</strong>' actualizado correctamente.";
}

// 2. Procesar Añadir Existencias según Receta con Descuento de Insumos y Validación de Límite
if (isset($_POST['accion']) && $_POST['accion'] == 'sumar_stock_receta') {
    $id_prod = (int)$_POST['id_producto'];
    $cantidad_solicitada = (int)$_POST['cantidad_sumar'];

    if ($cantidad_solicitada <= 0) {
        $error = "❌ La cantidad de existencias a agregar debe ser un número entero mayor a 0.";
    } else {
        $p_stmt = $conn->prepare("SELECT nombre, stock FROM productos WHERE id = ?");
        $p_stmt->bind_param("i", $id_prod);
        $p_stmt->execute();
        $prod = $p_stmt->get_result()->fetch_assoc();

        if (!$prod) {
            $error = "❌ Producto no encontrado.";
        } else {
            $nombre_prod = $prod['nombre'];

            // Consultar la receta del producto
            $r_stmt = $conn->prepare("
                SELECT r.ingrediente_id, r.cantidad_necesaria, i.nombre, i.stock_actual, i.unidad_medida 
                FROM recetas r 
                JOIN ingredientes i ON r.ingrediente_id = i.id 
                WHERE r.producto_id = ?
            ");
            $r_stmt->bind_param("i", $id_prod);
            $r_stmt->execute();
            $receta = $r_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if (empty($receta)) {
                $error = "⚠️ El producto '<strong>" . htmlspecialchars($nombre_prod) . "</strong>' no tiene una receta registrada en el sistema.<br>Debes asignarle sus ingredientes en la pestaña <a href='inventario.php#recetas' style='color:#d87093; font-weight:bold;'>Inventario y Recetas</a> antes de poder elaborar existencias con descuento de insumos.";
            } else {
                // Calcular cuántos productos pueden elaborarse con el stock de cada ingrediente
                $max_elaborables = PHP_INT_MAX;
                $insuficientes = [];

                foreach ($receta as $ing) {
                    $stock_disp = (float)$ing['stock_actual'];
                    $cant_necesaria = (float)$ing['cantidad_necesaria'];
                    $requerido_total = $cant_necesaria * $cantidad_solicitada;

                    $max_este_ing = ($cant_necesaria > 0) ? (int)floor($stock_disp / $cant_necesaria) : PHP_INT_MAX;
                    if ($max_este_ing < $max_elaborables) {
                        $max_elaborables = $max_este_ing;
                    }

                    if ($stock_disp < $requerido_total) {
                        $faltante = $requerido_total - $stock_disp;
                        $insuficientes[] = "<strong>" . htmlspecialchars($ing['nombre']) . "</strong> (disponible: " . number_format($stock_disp, 2) . " " . $ing['unidad_medida'] . ", requerido: " . number_format($requerido_total, 2) . " " . $ing['unidad_medida'] . " - falta: " . number_format($faltante, 2) . " " . $ing['unidad_medida'] . ")";
                    }
                }

                if ($max_elaborables === PHP_INT_MAX) { $max_elaborables = 0; }

                // Si la cantidad solicitada supera los ingredientes disponibles: NO DEJAR REALIZAR EL PROCESO
                if (!empty($insuficientes) || $cantidad_solicitada > $max_elaborables) {
                    $error = "❌ <strong>No se pueden agregar $cantidad_solicitada existencia(s) a '" . htmlspecialchars($nombre_prod) . "':</strong><br>" .
                             "La cantidad solicitada supera los ingredientes disponibles en inventario.<br><br>" .
                             "📊 <strong>Capacidad máxima de producción:</strong> Con los insumos actuales solo se pueden elaborar un máximo de <strong>$max_elaborables</strong> unidad(es).<br><br>" .
                             "⚠️ <em>Detalle de insumos insuficientes:</em><br>• " . implode("<br>• ", $insuficientes);
                } else {
                    // Sí alcanza: realizar transacción
                    $conn->begin_transaction();
                    try {
                        // 1. Descontar ingredientes según receta
                        $upd_ing = $conn->prepare("UPDATE ingredientes SET stock_actual = stock_actual - ? WHERE id = ?");
                        foreach ($receta as $ing) {
                            $descuento = (float)$ing['cantidad_necesaria'] * $cantidad_solicitada;
                            $id_ing = (int)$ing['ingrediente_id'];
                            $upd_ing->bind_param("di", $descuento, $id_ing);
                            $upd_ing->execute();
                        }

                        // 2. Aumentar stock del producto
                        $upd_prod = $conn->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                        $upd_prod->bind_param("ii", $cantidad_solicitada, $id_prod);
                        $upd_prod->execute();

                        $conn->commit();
                        $mensaje = "✅ <strong>¡Existencias agregadas con éxito!</strong> Se sumaron <strong>$cantidad_solicitada</strong> unidades al stock de '" . htmlspecialchars($nombre_prod) . "' y se descontaron los insumos correspondientes de bodega según su receta.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Error al actualizar existencias: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// 3. Procesar Eliminar Producto
if (isset($_GET['eliminar'])) {
    $id_eliminar = (int)$_GET['eliminar'];
    $res = $conn->query("SELECT imagen FROM productos WHERE id = $id_eliminar");
    if ($res->num_rows > 0) {
        $prod = $res->fetch_assoc();
        if ($prod['imagen'] != 'default.jpg' && file_exists(__DIR__ . "/assets/img/" . $prod['imagen'])) {
            unlink(__DIR__ . "/assets/img/" . $prod['imagen']);
        }
    }
    $conn->query("DELETE FROM productos WHERE id = $id_eliminar");
    $mensaje = "🗑️ Producto eliminado del catálogo.";
}

// 4. Mapear recetas y capacidades máximas de todos los productos para la vista
$recetas_map = [];
$res_rec = $conn->query("
    SELECT r.producto_id, r.cantidad_necesaria, i.nombre as ingrediente, i.stock_actual, i.unidad_medida 
    FROM recetas r 
    JOIN ingredientes i ON r.ingrediente_id = i.id
    ORDER BY r.producto_id, i.nombre ASC
");
while ($r = $res_rec->fetch_assoc()) {
    $recetas_map[$r['producto_id']][] = $r;
}

// 5. Lógica del Buscador y Filtros
$categoria_actual = $_GET['cat'] ?? 'Todas';
$busqueda = trim($_GET['buscar'] ?? '');

$sql = "SELECT * FROM productos WHERE 1=1";
if ($categoria_actual != 'Todas') {
    $sql .= " AND categoria = '" . $conn->real_escape_string($categoria_actual) . "'";
}
if (!empty($busqueda)) {
    $b_esc = $conn->real_escape_string($busqueda);
    $sql .= " AND (nombre LIKE '%$b_esc%' OR descripcion LIKE '%$b_esc%')";
}
$sql .= " ORDER BY categoria, nombre ASC";
$resultado = $conn->query($sql);

require 'includes/header.php';
?>

<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: var(--rosa-oscuro); margin-bottom: 5px;">🛠️ Panel de Administración</h2>
    <p style="color: #666; font-size: 0.9em; margin-top: 0; margin-bottom: 15px;">
        Gestiona catálogo, añade existencias con deducción automática de recetas y edita productos.
    </p>
    
    <!-- Botones de Acción Rápida -->
    <div style="display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
        <a href="index.php" class="btn" style="background: #8d6e63;">⬅️ Volver al Menú</a>
        <a href="admin.php" class="btn" style="background: #e91e63;">➕ Nuevo Producto</a>
        <a href="inventario.php" class="btn" style="background: #32cd32;">🥣 Gestionar Insumos y Recetas</a>

        <!-- Buscador -->
        <form method="GET" action="editar.php" style="display: inline-flex; gap: 8px;">
            <input type="hidden" name="cat" value="<?php echo htmlspecialchars($categoria_actual); ?>">
            <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar producto..." style="padding: 8px 12px; border-radius: 5px; border: 1px solid var(--rosa-fuerte); width: 220px;">
            <button type="submit" class="btn">🔍</button>
        </form>
    </div>
</div>

<!-- Filtros por Categoría -->
<div class="filtros" style="margin-bottom: 25px;">
    <?php 
    $cats = ['Todas', 'Panes', 'Masitas', 'Galletas', 'Postres', 'Bebidas'];
    foreach($cats as $c) {
        $active = ($categoria_actual == $c) ? 'active' : '';
        echo "<a href='editar.php?cat=" . urlencode($c) . "&buscar=" . urlencode($busqueda) . "' class='$active' style='font-size: 0.9em; padding: 6px 14px;'>$c</a>";
    }
    ?>
</div>

<!-- Mensajes de Notificación -->
<?php if(isset($mensaje)): ?>
    <div style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <?php echo $mensaje; ?>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Tabla de Productos -->
<div class="table-responsive">
<table style="box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
    <thead>
        <tr>
            <th style="width: 70px;">Imagen</th>
            <th>Producto</th>
            <th>Precio</th>
            <th style="text-align: center;">Stock</th>
            <th>Receta & Capacidad</th>
            <th style="text-align: center; min-width: 320px; white-space: nowrap;">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $resultado->fetch_assoc()): 
            $p_id = $row['id'];
            $tiene_receta = isset($recetas_map[$p_id]) && count($recetas_map[$p_id]) > 0;
            $max_elaborables = 0;
            $receta_items = [];

            if ($tiene_receta) {
                $max_elaborables = PHP_INT_MAX;
                foreach ($recetas_map[$p_id] as $rec) {
                    $cant_nec = (float)$rec['cantidad_necesaria'];
                    $stk_act = (float)$rec['stock_actual'];
                    $cap = ($cant_nec > 0) ? (int)floor($stk_act / $cant_nec) : PHP_INT_MAX;
                    if ($cap < $max_elaborables) {
                        $max_elaborables = $cap;
                    }
                    $receta_items[] = [
                        'ingrediente' => $rec['ingrediente'],
                        'cantidad' => $cant_nec,
                        'unidad' => $rec['unidad_medida'],
                        'stock_actual' => $stk_act
                    ];
                }
                if ($max_elaborables === PHP_INT_MAX) $max_elaborables = 0;
            }
            $json_receta = htmlspecialchars(json_encode($receta_items), ENT_QUOTES, 'UTF-8');
        ?>
        <tr>
            <td>
                <img src="assets/img/<?php echo htmlspecialchars($row['imagen']); ?>" style="width: 55px; height: 55px; object-fit: cover; border-radius: 8px; border: 1px solid #ffd1dc;" onerror="this.src='assets/img/default.jpg'">
            </td>
            <td>
                <strong style="color: var(--texto); font-size: 1.05em;"><?php echo htmlspecialchars($row['nombre']); ?></strong><br>
                <span class="categoria-tag" style="margin-top: 4px;"><?php echo htmlspecialchars($row['categoria']); ?></span>
            </td>
            <td style="font-weight: bold; color: #d87093;">
                Bs <?php echo number_format($row['precio'], 2, ',', '.'); ?>
            </td>
            <td style="text-align: center;">
                <span style="font-size: 1.15em; font-weight: bold; padding: 4px 10px; border-radius: 12px; background: <?php echo ($row['stock'] > 5) ? '#e8f5e9' : (($row['stock'] > 0) ? '#fff8e1' : '#ffebee'); ?>; color: <?php echo ($row['stock'] > 5) ? '#2e7d32' : (($row['stock'] > 0) ? '#f57f17' : '#c62828'); ?>;">
                    <?php echo $row['stock']; ?>
                </span>
            </td>
            <td>
                <?php if ($tiene_receta): ?>
                    <span style="font-size: 0.85em; color: #555;">
                        🥣 <?php echo count($recetas_map[$p_id]); ?> ingrediente(s)<br>
                        <strong style="color: <?php echo ($max_elaborables > 0) ? '#28a745' : '#dc3545'; ?>;">
                            Max elaborable: <?php echo $max_elaborables; ?> u.
                        </strong>
                    </span>
                <?php else: ?>
                    <span style="font-size: 0.85em; color: #e65100; background: #fff3e0; padding: 3px 6px; border-radius: 4px;">
                        ⚠️ Sin receta
                    </span>
                <?php endif; ?>
            </td>
            <td style="text-align: center; white-space: nowrap;">
                <div style="display: inline-flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: nowrap; white-space: nowrap;">
                    
                    <!-- BOTÓN AGREGAR EXISTENCIA -->
                    <button type="button" 
                            class="btn" 
                            style="background: #28a745; padding: 6px 12px; font-size: 0.85em; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;"
                            onclick='abrirModalExistencia(<?php echo $p_id; ?>, "<?php echo addslashes(htmlspecialchars($row['nombre'])); ?>", <?php echo $row['stock']; ?>, <?php echo $tiene_receta ? "true" : "false"; ?>, <?php echo $max_elaborables; ?>, <?php echo $json_receta; ?>)'>
                        ➕ Existencia
                    </button>

                    <!-- BOTÓN EDITAR -->
                    <a href="editar.php?id=<?php echo $row['id']; ?>" class="btn" style="background: #d87093; padding: 6px 12px; font-size: 0.85em; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                        ✏️ Editar
                    </a>

                    <!-- BOTÓN ELIMINAR -->
                    <a href="editar.php?eliminar=<?php echo $row['id']; ?>" class="btn" style="background: #ff4d4d; padding: 6px 12px; font-size: 0.85em; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;" onclick="return confirm('¿Estás seguro de que quieres eliminar \'<?php echo addslashes(htmlspecialchars($row['nombre'])); ?>\'? Se borrará también su imagen asociada.');">
                        🗑️ Eliminar
                    </a>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<!-- MODAL PARA AGREGAR EXISTENCIA CON DESCUENTO DE RECETA -->
<div id="modalExistencia" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.55); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(2px);">
    <div style="background: white; border-radius: 15px; padding: 25px; width: 90%; max-width: 520px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); border-top: 6px solid #28a745; position: relative; max-height: 90vh; overflow-y: auto;">
        
        <h3 id="modalTitulo" style="color: #28a745; margin-top: 0; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            ➕ Agregar Existencias
        </h3>
        
        <p style="color: #555; font-size: 0.95em; margin-bottom: 15px;">
            Producto: <strong id="modalNombreProd" style="color: var(--rosa-oscuro);"></strong><br>
            Stock actual: <strong id="modalStockActual"></strong> unidades
        </p>

        <!-- Bloque informativo de receta y capacidad -->
        <div id="modalInfoReceta" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px; margin-bottom: 15px; font-size: 0.88em;">
            <!-- Rellenado por JavaScript -->
        </div>

        <form method="POST" action="editar.php" id="formAgregarExistencia" onsubmit="return validarModalExistencia();">
            <input type="hidden" name="accion" value="sumar_stock_receta">
            <input type="hidden" name="id_producto" id="modalIdProd" value="">

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 6px; color: var(--texto);">
                    Cantidad de productos a elaborar / agregar: <span style="color: red;">*</span>
                </label>
                <input type="number" 
                       name="cantidad_sumar" 
                       id="modalInputCantidad" 
                       value="1" 
                       min="1" 
                       required 
                       style="width: 100%; padding: 10px; border: 2px solid var(--rosa-fuerte); border-radius: 6px; font-size: 1.1em; font-weight: bold;"
                       oninput="calcularRequerimientos()">
                <small style="color: #666; display: block; margin-top: 4px;">
                    Al confirmar, los ingredientes de la receta se descontarán automáticamente del almacén.
                </small>
            </div>

            <!-- Previsualización dinámica de insumos a descontar -->
            <div id="modalDesgloseDescuento" style="display: none; background: #fff5f8; border: 1px solid #ffd1dc; border-radius: 8px; padding: 10px; margin-bottom: 18px; font-size: 0.85em;">
                <!-- Dinámico -->
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: #888;" onclick="cerrarModalExistencia()">Cancelar</button>
                <button type="submit" id="btnConfirmarExistencia" class="btn" style="background: #28a745;">
                    ✅ Elaborar y Descontar Insumos
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentReceta = [];
let currentMax = 0;
let tieneRecetaActiva = false;

function abrirModalExistencia(id, nombre, stock, tieneReceta, maxElaborables, recetaItems) {
    document.getElementById('modalIdProd').value = id;
    document.getElementById('modalNombreProd').textContent = nombre;
    document.getElementById('modalStockActual').textContent = stock;
    document.getElementById('modalInputCantidad').value = 1;
    
    currentReceta = recetaItems || [];
    currentMax = maxElaborables || 0;
    tieneRecetaActiva = tieneReceta;

    const infoDiv = document.getElementById('modalInfoReceta');
    const btnConfirmar = document.getElementById('btnConfirmarExistencia');

    if (!tieneReceta || currentReceta.length === 0) {
        infoDiv.innerHTML = `
            <div style="color: #c62828; font-weight: bold;">
                ⚠️ Este producto no tiene receta registrada en el sistema.
            </div>
            <p style="margin: 6px 0 0 0; color: #666;">
                Debes asignarle una receta en la sección de <a href="inventario.php" style="color: var(--rosa-oscuro); text-decoration: underline;">Inventario</a> antes de poder elaborar existencias con descuento de ingredientes.
            </p>
        `;
        btnConfirmar.disabled = true;
        btnConfirmar.style.opacity = '0.5';
        btnConfirmar.style.cursor = 'not-allowed';
    } else {
        btnConfirmar.disabled = false;
        btnConfirmar.style.opacity = '1';
        btnConfirmar.style.cursor = 'pointer';

        let listaIngHTML = currentReceta.map(item => `• ${item.ingrediente}: <strong>${parseFloat(item.cantidad)} ${item.unidad}</strong> por unidad (Disp: ${parseFloat(item.stock_actual).toFixed(2)} ${item.unidad})`).join('<br>');
        
        infoDiv.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <strong style="color: var(--rosa-oscuro);">🥣 Receta por unidad:</strong>
                <span style="font-weight: bold; padding: 2px 8px; border-radius: 8px; background: ${currentMax > 0 ? '#e8f5e9' : '#ffebee'}; color: ${currentMax > 0 ? '#2e7d32' : '#c62828'};">
                    Máx elaborable hoy: ${currentMax} u.
                </span>
            </div>
            <div style="color: #444; line-height: 1.4;">${listaIngHTML}</div>
        `;
    }

    calcularRequerimientos();
    const modal = document.getElementById('modalExistencia');
    modal.style.display = 'flex';
}

function cerrarModalExistencia() {
    document.getElementById('modalExistencia').style.display = 'none';
}

// Cerrar al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalExistencia');
    if (event.target === modal) {
        cerrarModalExistencia();
    }
}

function calcularRequerimientos() {
    const inputCant = document.getElementById('modalInputCantidad');
    const cant = parseInt(inputCant.value) || 0;
    const desglose = document.getElementById('modalDesgloseDescuento');
    const btnConfirmar = document.getElementById('btnConfirmarExistencia');

    if (!tieneRecetaActiva || currentReceta.length === 0) {
        desglose.style.display = 'none';
        return;
    }

    if (cant <= 0) {
        desglose.style.display = 'none';
        return;
    }

    let detalleHTML = `<strong>Descuento proyectado de insumos (${cant} u.):</strong><br>`;
    let alcanza = true;

    currentReceta.forEach(item => {
        const totalReq = item.cantidad * cant;
        const disponible = item.stock_actual;
        const suficiente = (disponible >= totalReq);
        if (!suficiente) alcanza = false;

        detalleHTML += `
            <div style="display: flex; justify-content: space-between; margin-top: 3px; color: ${suficiente ? '#2e7d32' : '#c62828'};">
                <span>${item.ingrediente}: -${totalReq.toFixed(2)} ${item.unidad}</span>
                <span>(Disp: ${disponible.toFixed(2)} ${item.unidad}) ${suficiente ? '✅' : '❌ Insuficiente'}</span>
            </div>
        `;
    });

    if (!alcanza) {
        detalleHTML += `
            <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #ff4d4d; color: #c62828; font-weight: bold;">
                ⚠️ La cantidad supera los ingredientes en almacén. Solo puedes elaborar un máximo de ${currentMax} unidad(es).
            </div>
        `;
        btnConfirmar.style.background = '#d9534f';
    } else {
        btnConfirmar.style.background = '#28a745';
    }

    desglose.innerHTML = detalleHTML;
    desglose.style.display = 'block';
}

function validarModalExistencia() {
    if (!tieneRecetaActiva) {
        alert("⚠️ No puedes agregar existencias porque este producto no tiene una receta registrada.");
        return false;
    }
    const cant = parseInt(document.getElementById('modalInputCantidad').value) || 0;
    if (cant <= 0) {
        alert("❌ Por favor ingresa una cantidad válida mayor a 0.");
        return false;
    }
    if (cant > currentMax) {
        alert(`❌ No se puede realizar el proceso:\nLa cantidad solicitada (${cant}) supera los ingredientes disponibles en inventario.\nCon el stock actual de insumos solo se pueden elaborar ${currentMax} unidad(es).`);
        return false;
    }
    return true;
}
</script>

<!-- Formulario de Edición de Producto (Solo si se seleccionó 'Editar') -->
<?php if(isset($_GET['id'])): 
    $id_editar = (int)$_GET['id'];
    $prod = $conn->query("SELECT * FROM productos WHERE id = $id_editar")->fetch_assoc();
    if($prod):
?>
<div class="form-container" style="margin-top: 40px; border-top: 5px solid var(--rosa-fuerte); max-width: 550px;">
    <h3 style="color: var(--rosa-oscuro); text-align: center; margin-top: 0;">✏️ Editando: <?php echo htmlspecialchars($prod['nombre']); ?></h3>
    
    <form method="POST" action="editar.php" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
        
        <div class="form-group">
            <label>Nombre:</label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($prod['nombre']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Descripción: <small style="color: #888; font-weight: normal;">(Opcional)</small></label>
            <textarea name="descripcion" rows="3"><?php echo htmlspecialchars($prod['descripcion']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Precio (Bs):</label>
            <input type="number" step="0.01" name="precio" value="<?php echo $prod['precio']; ?>" required>
        </div>
        
        <div class="form-group">
            <label>Categoría:</label>
            <select name="categoria">
                <?php 
                $cats = ['Panes', 'Masitas', 'Galletas', 'Postres', 'Bebidas'];
                foreach($cats as $c) {
                    $sel = ($prod['categoria'] == $c) ? 'selected' : '';
                    echo "<option value='$c' $sel>$c</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Stock Exacto (Reemplaza el actual sin descontar insumos):</label>
            <input type="number" name="stock" value="<?php echo $prod['stock']; ?>" required>
            <small style="color: #888;">Para agregar existencias descontando insumos automáticamente de su receta, usa el botón verde <strong>➕ Existencia</strong> en la tabla.</small>
        </div>
        
        <div class="form-group">
            <label>Cambiar Imagen:</label>
            <input type="file" name="imagen" accept="image/*">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <a href="editar.php" class="btn" style="background: #888; flex: 1; text-align: center;">Cancelar</a>
            <button type="submit" class="btn" style="flex: 2;">💾 Guardar Cambios</button>
        </div>
    </form>
</div>
<?php endif; endif; ?>

<?php require 'includes/footer.php'; ?>
