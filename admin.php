<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'includes/conexion.php';

// Obtener ingredientes disponibles para el selector de recetas
$res_ingredientes = $conn->query("SELECT id, nombre, unidad_medida, stock_actual FROM ingredientes ORDER BY nombre ASC");
$ingredientes_disponibles = [];
while ($row = $res_ingredientes->fetch_assoc()) {
    $ingredientes_disponibles[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? ''); // NO OBLIGATORIA
    $precio = (float)($_POST['precio'] ?? 0);
    $categoria = $_POST['categoria'] ?? 'Panes';
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    if ($stock < 0) { $stock = 0; }
    
    // Validar datos básicos
    if (empty($nombre) || $precio <= 0) {
        $error = "Por favor ingresa un nombre y un precio válido mayor a 0.";
    }

    // Validar receta OBLIGATORIA
    $ingredientes_post = $_POST['ingrediente_id'] ?? [];
    $cantidades_post = $_POST['cantidad_ingrediente'] ?? [];

    $receta_valida = [];
    if (is_array($ingredientes_post)) {
        foreach ($ingredientes_post as $idx => $ing_id) {
            $ing_id = (int)$ing_id;
            $cant = isset($cantidades_post[$idx]) ? (float)$cantidades_post[$idx] : 0;
            if ($ing_id > 0 && $cant > 0) {
                if (isset($receta_valida[$ing_id])) {
                    $receta_valida[$ing_id] += $cant;
                } else {
                    $receta_valida[$ing_id] = $cant;
                }
            }
        }
    }

    if (empty($error) && empty($receta_valida)) {
        $error = "❌ <strong>La receta es obligatoria:</strong> Debes añadir al menos un ingrediente con su cantidad requerida para elaborar este producto.";
    }

    // Si el usuario especificó stock inicial > 0, verificar si alcanzan los ingredientes en almacén
    if (empty($error) && $stock > 0) {
        $max_posible_inicial = PHP_INT_MAX;
        $faltantes_inicial = [];
        
        foreach ($receta_valida as $ing_id => $cant_necesaria) {
            $stmt_chk = $conn->prepare("SELECT nombre, stock_actual, unidad_medida FROM ingredientes WHERE id = ?");
            $stmt_chk->bind_param("i", $ing_id);
            $stmt_chk->execute();
            $ing_q = $stmt_chk->get_result()->fetch_assoc();
            
            $stock_disp = (float)$ing_q['stock_actual'];
            $req = $cant_necesaria * $stock;
            
            $cap = ($cant_necesaria > 0) ? (int)floor($stock_disp / $cant_necesaria) : PHP_INT_MAX;
            if ($cap < $max_posible_inicial) {
                $max_posible_inicial = $cap;
            }
            if ($stock_disp < $req) {
                $faltante = $req - $stock_disp;
                $faltantes_inicial[] = "<strong>" . htmlspecialchars($ing_q['nombre']) . "</strong> (disponible: " . number_format($stock_disp, 2) . " " . $ing_q['unidad_medida'] . ", requerido: " . number_format($req, 2) . " " . $ing_q['unidad_medida'] . " - falta: " . number_format($faltante, 2) . " " . $ing_q['unidad_medida'] . ")";
            }
        }
        
        if ($max_posible_inicial === PHP_INT_MAX) $max_posible_inicial = 0;
        
        if ($stock > $max_posible_inicial) {
            $error = "❌ <strong>No se puede crear el producto con un stock inicial de $stock unidades:</strong><br>" .
                     "La cantidad supera los ingredientes disponibles en almacén.<br><br>" .
                     "📊 <strong>Capacidad máxima con ingredientes actuales:</strong> Solo se pueden elaborar <strong>$max_posible_inicial</strong> unidad(es).<br><br>" .
                     "⚠️ <em>Insumos insuficientes:</em><br>• " . implode("<br>• ", $faltantes_inicial) . "<br><br>" .
                     "💡 <em>Sugerencia:</em> Puedes crear el producto con un stock de <strong>0</strong> o hasta <strong>$max_posible_inicial</strong>, y más adelante fabricar más existencias desde el panel de administración.";
        }
    }

    if (empty($error)) {
        // Manejo de imagen
        $imagen_nombre = 'default.jpg';
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            $directorio = __DIR__ . "/assets/img/";
            $nombre_archivo = preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['imagen']['name']));
            $imagen_nombre = time() . "_" . $nombre_archivo; 
            move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre);
        }

        $conn->begin_transaction();
        try {
            // 1. Guardar producto (con descripción no obligatoria)
            $stmt = $conn->prepare("INSERT INTO productos (nombre, descripcion, precio, categoria, stock, imagen) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdsis", $nombre, $descripcion, $precio, $categoria, $stock, $imagen_nombre);
            $stmt->execute();
            $nuevo_id = $conn->insert_id;

            // 2. Guardar receta obligatoria
            $stmt_rec = $conn->prepare("INSERT INTO recetas (producto_id, ingrediente_id, cantidad_necesaria) VALUES (?, ?, ?)");
            $stmt_desc = $conn->prepare("UPDATE ingredientes SET stock_actual = stock_actual - ? WHERE id = ?");
            
            foreach ($receta_valida as $ing_id => $cant_necesaria) {
                $stmt_rec->bind_param("iid", $nuevo_id, $ing_id, $cant_necesaria);
                $stmt_rec->execute();

                // 3. Si se definió stock inicial > 0, descontar ingredientes de almacén
                if ($stock > 0) {
                    $descuento = $cant_necesaria * $stock;
                    $stmt_desc->bind_param("di", $descuento, $ing_id);
                    $stmt_desc->execute();
                }
            }

            $conn->commit();
            $mensaje = "✅ ¡Producto '<strong>" . htmlspecialchars($nombre) . "</strong>' y su receta fueron registrados con éxito!";
            if ($stock > 0) {
                $mensaje .= " Se descontaron los ingredientes del stock inicial ($stock unidades elaboradas).";
            }
            // Limpiar valores del formulario tras éxito
            $nombre = $descripcion = '';
            $precio = '';
            $stock = 0;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error al guardar el producto: " . $e->getMessage();
        }
    }
}
require 'includes/header.php';
?>

<div class="form-container" style="max-width: 650px; box-sizing: border-box; overflow: hidden;">
    <h2 style="color: var(--rosa-oscuro); text-align: center; margin-top: 0;">➕ Agregar Nuevo Producto</h2>
    <p style="text-align: center; color: #777; font-size: 0.9em; margin-top: -10px; margin-bottom: 20px;">
        Registra el producto en el catálogo y define obligatoriamente su receta para el control de inventario.
    </p>

    <?php if(isset($mensaje)): ?>
        <div style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="admin.php" enctype="multipart/form-data" id="formNuevoProducto" onsubmit="return validarFormulario();">
        <div class="form-group">
            <label>Nombre del Producto: <span style="color: red;">*</span></label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre ?? ''); ?>" required placeholder="Ej. Tarta de Manzana Real">
        </div>

        <div class="form-group">
            <label>Descripción: <small style="color: #888; font-weight: normal;">(Opcional)</small></label>
            <textarea name="descripcion" rows="2" placeholder="Detalles, notas de cata o ingredientes especiales (no obligatorio)"><?php echo htmlspecialchars($descripcion ?? ''); ?></textarea>
        </div>

        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 140px;">
                <label>Precio (Bs): <span style="color: red;">*</span></label>
                <input type="number" step="0.01" min="0.10" name="precio" value="<?php echo htmlspecialchars($precio ?? ''); ?>" required placeholder="0.00">
            </div>

            <div class="form-group" style="flex: 1; min-width: 140px;">
                <label>Categoría: <span style="color: red;">*</span></label>
                <select name="categoria">
                    <?php 
                    $categorias = ['Panes', 'Masitas', 'Galletas', 'Postres', 'Bebidas'];
                    $cat_sel = $_POST['categoria'] ?? 'Panes';
                    foreach($categorias as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo ($cat_sel == $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="flex: 1; min-width: 140px;">
                <label>Existencias Iniciales: <small style="color:#666;">(Stock)</small></label>
                <input type="number" name="stock" id="inputStockInicial" value="<?php echo htmlspecialchars($stock ?? '0'); ?>" min="0" placeholder="0">
                <small style="color: #888; font-size: 0.8em;">Si es mayor a 0, se descontarán insumos de bodega.</small>
            </div>
        </div>

        <div class="form-group">
            <label>Imagen del Producto:</label>
            <input type="file" name="imagen" accept="image/*">
        </div>

        <!-- SECCIÓN DE RECETA OBLIGATORIA -->
        <div style="background: #fff5f8; border: 2px dashed var(--rosa-fuerte); border-radius: 12px; padding: 18px; margin: 25px 0 20px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="color: var(--rosa-oscuro); margin: 0; font-size: 1.15em; display: flex; align-items: center; gap: 8px;">
                    🥣 Receta del Producto <span style="background: #e91e63; color: white; font-size: 0.7em; padding: 2px 8px; border-radius: 10px; font-weight: bold;">OBLIGATORIA</span>
                </h3>
            </div>
            <p style="color: #666; font-size: 0.88em; margin-top: 0; margin-bottom: 15px;">
                Selecciona los ingredientes y las cantidades exactas necesarias para elaborar <strong>1 unidad</strong> de este producto.
            </p>

            <div id="contenedor-receta">
                <!-- Fila inicial de ingrediente perfectamente contenida -->
                <div class="fila-receta" style="display: flex; gap: 8px; align-items: center; margin-bottom: 10px; background: white; padding: 10px; border-radius: 8px; border: 1px solid #ffd1dc; width: 100%; box-sizing: border-box; overflow: hidden;">
                    <select name="ingrediente_id[]" class="select-ing" required style="flex: 2; min-width: 0; width: 0; padding: 8px 6px; border-radius: 6px; border: 1px solid var(--rosa-fuerte); font-size: 0.9em; box-sizing: border-box;" onchange="cambiarUnidad(this)">
                        <option value="">-- Seleccionar Ingrediente --</option>
                        <?php foreach($ingredientes_disponibles as $ing): ?>
                            <option value="<?php echo $ing['id']; ?>" data-unidad="<?php echo htmlspecialchars($ing['unidad_medida']); ?>" data-stock="<?php echo $ing['stock_actual']; ?>">
                                <?php echo htmlspecialchars($ing['nombre']); ?> (Disp: <?php echo number_format($ing['stock_actual'], 2) . ' ' . $ing['unidad_medida']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="number" step="0.001" min="0.001" name="cantidad_ingrediente[]" placeholder="Cant." required style="flex: 1; min-width: 0; width: 0; max-width: 100px; padding: 8px 6px; border-radius: 6px; border: 1px solid var(--rosa-fuerte); font-size: 0.9em; box-sizing: border-box; text-align: center;">

                    <span class="label-unidad" style="min-width: 35px; width: 35px; font-size: 0.85em; font-weight: bold; color: var(--rosa-oscuro); text-align: center; flex-shrink: 0;">-</span>

                    <button type="button" class="btn" style="background: #ff4d4d; color: white; width: 36px; height: 36px; min-width: 36px; padding: 0; border: none; border-radius: 6px; cursor: pointer; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;" onclick="eliminarFila(this)" title="Eliminar ingrediente">🗑️</button>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                <button type="button" class="btn" onclick="agregarFila()" style="background: #32cd32; font-size: 0.9em; padding: 8px 15px;">
                    ➕ Agregar otro ingrediente
                </button>
                <small style="color: #777;">Mínimo 1 ingrediente obligatorio.</small>
            </div>
        </div>

        <button type="submit" class="btn" style="width: 100%; padding: 12px; font-size: 1.1em; background: var(--rosa-oscuro);">
            💾 Guardar Producto con su Receta
        </button>
    </form>
</div>

<script>
const ingredientesJSON = <?php echo json_encode($ingredientes_disponibles); ?>;

function cambiarUnidad(selectElem) {
    const fila = selectElem.closest('.fila-receta');
    const labelUnidad = fila.querySelector('.label-unidad');
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const unidad = selectedOption.getAttribute('data-unidad');
    if (unidad) {
        labelUnidad.textContent = unidad;
    } else {
        labelUnidad.textContent = '-';
    }
}

function agregarFila() {
    const contenedor = document.getElementById('contenedor-receta');
    const div = document.createElement('div');
    div.className = 'fila-receta';
    div.style = 'display: flex; gap: 8px; align-items: center; margin-bottom: 10px; background: white; padding: 10px; border-radius: 8px; border: 1px solid #ffd1dc; width: 100%; box-sizing: border-box; overflow: hidden;';
    
    let optionsHTML = '<option value="">-- Seleccionar Ingrediente --</option>';
    ingredientesJSON.forEach(ing => {
        optionsHTML += `<option value="${ing.id}" data-unidad="${ing.unidad_medida}" data-stock="${ing.stock_actual}">${ing.nombre} (Disp: ${parseFloat(ing.stock_actual).toFixed(2)} ${ing.unidad_medida})</option>`;
    });

    div.innerHTML = `
        <select name="ingrediente_id[]" class="select-ing" required style="flex: 2; min-width: 0; width: 0; padding: 8px 6px; border-radius: 6px; border: 1px solid var(--rosa-fuerte); font-size: 0.9em; box-sizing: border-box;" onchange="cambiarUnidad(this)">
            ${optionsHTML}
        </select>
        <input type="number" step="0.001" min="0.001" name="cantidad_ingrediente[]" placeholder="Cant." required style="flex: 1; min-width: 0; width: 0; max-width: 100px; padding: 8px 6px; border-radius: 6px; border: 1px solid var(--rosa-fuerte); font-size: 0.9em; box-sizing: border-box; text-align: center;">
        <span class="label-unidad" style="min-width: 35px; width: 35px; font-size: 0.85em; font-weight: bold; color: var(--rosa-oscuro); text-align: center; flex-shrink: 0;">-</span>
        <button type="button" class="btn" style="background: #ff4d4d; color: white; width: 36px; height: 36px; min-width: 36px; padding: 0; border: none; border-radius: 6px; cursor: pointer; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 16px;" onclick="eliminarFila(this)" title="Eliminar ingrediente">🗑️</button>
    `;
    contenedor.appendChild(div);
}

function eliminarFila(btn) {
    const contenedor = document.getElementById('contenedor-receta');
    const filas = contenedor.querySelectorAll('.fila-receta');
    if (filas.length <= 1) {
        alert("⚠️ La receta es obligatoria. Debes conservar al menos un ingrediente para este producto.");
        return;
    }
    btn.closest('.fila-receta').remove();
}

function validarFormulario() {
    const contenedor = document.getElementById('contenedor-receta');
    const filas = contenedor.querySelectorAll('.fila-receta');
    let ingredientesSeleccionados = 0;

    for (let i = 0; i < filas.length; i++) {
        const select = filas[i].querySelector('.select-ing');
        const inputCant = filas[i].querySelector('input[name="cantidad_ingrediente[]"]');
        if (select.value !== "" && parseFloat(inputCant.value) > 0) {
            ingredientesSeleccionados++;
        }
    }

    if (ingredientesSeleccionados === 0) {
        alert("❌ Error: La receta es obligatoria. Debes seleccionar al menos un ingrediente con su cantidad.");
        return false;
    }

    return true;
}
</script>

<?php require 'includes/footer.php'; ?>
