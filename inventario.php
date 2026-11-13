<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'includes/conexion.php';

// --- 1. ELIMINAR INGREDIENTE ---
if (isset($_GET['eliminar_ingrediente'])) {
    $id_eliminar = $_GET['eliminar_ingrediente'];
    $conn->query("DELETE FROM ingredientes WHERE id = $id_eliminar");
    $mensaje = "Ingrediente eliminado del inventario.";
}

// --- 2. EDITAR INGREDIENTE (Desde modal inventario) ---
if (isset($_POST['editar_ingrediente'])) {
    $id_edit = $_POST['id_ingrediente_edit'];
    $nombre = $_POST['nombre_edit'];
    $unidad = $_POST['unidad_edit'];
    $stock = (float)$_POST['stock_edit'];
    $stock_min = (float)$_POST['stock_min_edit'];
    
    $conn->query("UPDATE ingredientes SET nombre='$nombre', unidad_medida='$unidad', stock_actual=$stock, stock_minimo=$stock_min WHERE id=$id_edit");
    $mensaje = "Ingrediente actualizado correctamente.";
}

// --- 3. ELIMINAR RECETA COMPLETA ---
if (isset($_GET['eliminar_receta_completa'])) {
    $prod_id = $_GET['eliminar_receta_completa'];
    $conn->query("DELETE FROM recetas WHERE producto_id = $prod_id");
    $mensaje_receta = "Receta eliminada por completo.";
}

// --- 4. ACTUALIZAR RECETA COMPLETA (Desde modal) ---
if (isset($_POST['actualizar_receta_completa'])) {
    $prod_id = $_POST['prod_id_edit'];
    
    // Borramos la receta anterior
    $conn->query("DELETE FROM recetas WHERE producto_id = $prod_id");
    
    // Insertamos la nueva
    if (isset($_POST['ingredientes_edit']) && is_array($_POST['ingredientes_edit'])) {
        $stmt = $conn->prepare("INSERT INTO recetas (producto_id, ingrediente_id, cantidad_necesaria) VALUES (?, ?, ?)");
        $agregados = 0;
        foreach ($_POST['ingredientes_edit'] as $index => $ing_id) {
            $cant = (float)$_POST['cantidades_edit'][$index];
            if (!empty($ing_id) && $cant > 0) {
                $stmt->bind_param("iid", $prod_id, $ing_id, $cant);
                $stmt->execute();
                $agregados++;
            }
        }
        if ($agregados > 0) $mensaje_receta = "Receta actualizada con $agregados ingredientes.";
    }
}

// --- 5. AÑADIR NUEVO INGREDIENTE ---
if (isset($_POST['nuevo_ingrediente'])) {
    $nombre = $_POST['nombre_ing'];
    $unidad = $_POST['unidad'];
    $stock_inicial = (float)$_POST['stock_inicial'];
    $stock_minimo = (float)$_POST['stock_minimo'];
    $proveedor = !empty($_POST['proveedor_id']) ? $_POST['proveedor_id'] : "NULL";

    if ($proveedor === "NULL") {
        $conn->query("INSERT INTO ingredientes (nombre, stock_actual, unidad_medida, stock_minimo) VALUES ('$nombre', $stock_inicial, '$unidad', $stock_minimo)");
    } else {
        $conn->query("INSERT INTO ingredientes (nombre, stock_actual, unidad_medida, stock_minimo, proveedor_id) VALUES ('$nombre', $stock_inicial, '$unidad', $stock_minimo, $proveedor)");
    }
    $mensaje = "¡Ingrediente '$nombre' registrado con éxito!";
}

// --- 6. AÑADIR RECETAS (MÚLTIPLES) ---
if (isset($_POST['agregar_recetas_multiples'])) {
    $prod_id = $_POST['producto_id'];
    if (isset($_POST['ingredientes']) && is_array($_POST['ingredientes'])) {
        $stmt = $conn->prepare("INSERT INTO recetas (producto_id, ingrediente_id, cantidad_necesaria) VALUES (?, ?, ?)");
        $agregados = 0;
        foreach ($_POST['ingredientes'] as $index => $ing_id) {
            $cant = (float)$_POST['cantidades'][$index];
            if (!empty($ing_id) && $cant > 0) {
                $stmt->bind_param("iid", $prod_id, $ing_id, $cant);
                $stmt->execute();
                $agregados++;
            }
        }
        if ($agregados > 0) $mensaje_receta = "Se agregaron $agregados ingredientes a la receta.";
    }
}

// --- 7. SUMAR STOCK PRODUCTO (FABRICAR) ---
if (isset($_POST['sumar_stock'])) {
    $id_prod = $_POST['id_producto'];
    $cantidad_prod = (int)$_POST['cantidad'];
    if ($cantidad_prod > 0) {
        $conn->query("UPDATE productos SET stock = stock + $cantidad_prod WHERE id = $id_prod");
        $receta_query = $conn->query("SELECT ingrediente_id, cantidad_necesaria FROM recetas WHERE producto_id = $id_prod");
        while ($ing = $receta_query->fetch_assoc()) {
            $ing_id = $ing['ingrediente_id'];
            $cant_necesaria = $ing['cantidad_necesaria'] * $cantidad_prod; 
            $conn->query("UPDATE ingredientes SET stock_actual = stock_actual - $cant_necesaria WHERE id = $ing_id");
        }
        $mensaje = "Se fabricaron $cantidad_prod productos y se descontaron ingredientes.";
    }
}

// --- 8. SUMAR STOCK DIRECTO A INGREDIENTE ---
if (isset($_POST['sumar_ingrediente'])) {
    $id_ing = $_POST['id_ingrediente'];
    $cantidad_ing = (float)$_POST['cantidad_ing'];
    if ($cantidad_ing > 0) {
        $conn->query("UPDATE ingredientes SET stock_actual = stock_actual + $cantidad_ing WHERE id = $id_ing");
        $mensaje = "Stock de ingrediente actualizado.";
    }
}

// Consultas para llenar las tablas
$ingredientes = $conn->query("SELECT i.*, p.nombre_empresa as proveedor FROM ingredientes i LEFT JOIN proveedores p ON i.proveedor_id = p.id ORDER BY i.nombre ASC");
$lista_productos = $conn->query("SELECT * FROM productos ORDER BY nombre ASC");
$lista_ingredientes_select = $conn->query("SELECT * FROM ingredientes ORDER BY nombre ASC");
$lista_proveedores = $conn->query("SELECT * FROM proveedores ORDER BY nombre_empresa ASC");

require 'includes/header.php';
?>

<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: var(--rosa-oscuro);">🥣 Inventario y Recetas</h2>
</div>

<?php if(isset($mensaje)) echo "<div style='background:#d4edda; color:#155724; padding:10px; border-radius:5px; text-align:center; margin-bottom:15px;'>$mensaje</div>"; ?>
<?php if(isset($mensaje_receta)) echo "<div style='background:#d4edda; color:#155724; padding:10px; border-radius:5px; text-align:center; margin-bottom:15px;'>$mensaje_receta</div>"; ?>

<!-- BOTONES PARA CAMBIAR DE VISTA -->
<div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 20px;">
    <button onclick="mostrarSeccion('inventario')" id="btn-inventario" class="btn" style="background: var(--rosa-oscuro); color: white;">📦 Ver Ingredientes</button>
    <button onclick="mostrarSeccion('recetas')" id="btn-recetas" class="btn" style="background: #ccc; color: #333;">📖 Ver Recetas</button>
</div>

<!-- ========================================== -->
<!-- SECCIÓN 1: INVENTARIO DE INGREDIENTES      -->
<!-- ========================================== -->
<div id="seccion-inventario">
    
    <!-- Formulario Nuevo Ingrediente -->
    <div class="form-container" style="max-width: 100%; margin-bottom: 30px; border-left: 5px solid #32cd32;">
        <h3 style="color: var(--rosa-oscuro); margin-top: 0;">➕ Registrar Nuevo Ingrediente</h3>
        <form method="POST" action="inventario.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex: 2; min-width: 200px; margin-bottom: 0;">
                <label>Nombre:</label>
                <input type="text" name="nombre_ing" required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
            </div>
            <div class="form-group" style="flex: 1; min-width: 120px; margin-bottom: 0;">
                <label>Unidad:</label>
                <select name="unidad" required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="kg">kg</option>
                    <option value="litros">Litros</option>
                    <option value="unidades">Unidades</option>
                    <option value="gramos">g</option>
                    <option value="ml">ml</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 100px; margin-bottom: 0;">
                <label>Stock Inicial:</label>
                <input type="number" step="0.1" name="stock_inicial" value="0" required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
            </div>
            <div class="form-group" style="flex: 1; min-width: 100px; margin-bottom: 0;">
                <label>Mínimo:</label>
                <input type="number" step="0.1" name="stock_minimo" value="5" required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
            </div>
            <div class="form-group" style="flex: 1.5; min-width: 150px; margin-bottom: 0;">
                <label>Proveedor:</label>
                <select name="proveedor_id" style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="">-- Ninguno --</option>
                    <?php while($prov = $lista_proveedores->fetch_assoc()): ?>
                        <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre_empresa']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" name="nuevo_ingrediente" class="btn" style="background: #32cd32; height: 38px;">Guardar</button>
        </form>
    </div>

    <h3 style="color: var(--rosa-oscuro); border-bottom: 2px solid var(--rosa-fuerte); padding-bottom: 10px;">Stock Actual de Ingredientes</h3>
    <table>
        <tr><th>Ingrediente</th><th>Stock Actual</th><th>Estado</th><th>Añadir Stock</th><th>Acciones</th></tr>
        <?php while($ing = $ingredientes->fetch_assoc()): 
            $alerta = ($ing['stock_actual'] <= $ing['stock_minimo']);
        ?>
        <tr style="<?php echo $alerta ? 'background:#ffe6e6;' : ''; ?>">
            <td><strong><?php echo htmlspecialchars($ing['nombre']); ?></strong><br><small><?php echo $ing['proveedor'] ? htmlspecialchars($ing['proveedor']) : 'Sin asignar'; ?></small></td>
            <td style="color: <?php echo $alerta ? '#ff4d4d' : '#32cd32'; ?>; font-weight: bold;">
                <?php echo number_format($ing['stock_actual'], 2, ',', '.') . ' ' . $ing['unidad_medida']; ?>
            </td>
            <td><?php echo $alerta ? '<span style="color:red; font-size:0.8em;">¡Bajo!</span>' : 'OK'; ?></td>
            <td>
                <form method="POST" style="display: flex; gap: 5px;">
                    <input type="hidden" name="id_ingrediente" value="<?php echo $ing['id']; ?>">
                    <input type="number" step="0.1" name="cantidad_ing" value="10" style="width: 50px; padding: 4px;">
                    <button type="submit" name="sumar_ingrediente" class="btn" style="background: #32cd32; padding: 4px 8px;">+</button>
                </form>
            </td>
            <td>
                <div style="display: flex; gap: 5px;">
                    <button onclick="abrirModalEditarIng(<?php echo $ing['id']; ?>, '<?php echo htmlspecialchars($ing['nombre'], ENT_QUOTES); ?>', '<?php echo $ing['unidad_medida']; ?>', <?php echo $ing['stock_actual']; ?>, <?php echo $ing['stock_minimo']; ?>)" class="btn" style="background: #d87093; padding: 4px 8px; font-size: 0.8em;">Editar</button>
                    <a href="inventario.php?eliminar_ingrediente=<?php echo $ing['id']; ?>" class="btn" style="background: red; padding: 4px 8px; font-size: 0.8em;" onclick="return confirm('¿Seguro que quieres eliminar este ingrediente?');">X</a>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<!-- MODAL PARA EDITAR INGREDIENTE -->
<div id="modalEditarIng" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 30px; border-radius: 15px; width: 400px; border: 3px solid var(--rosa-fuerte);">
        <h3 style="color: var(--rosa-oscuro); margin-top: 0;">Editar Ingrediente</h3>
        <form method="POST" action="inventario.php">
            <input type="hidden" name="id_ingrediente_edit" id="edit_id">
            <div class="form-group"><label>Nombre:</label><input type="text" name="nombre_edit" id="edit_nombre" required style="width: 100%; padding: 8px;"></div>
            <div class="form-group">
                <label>Unidad:</label>
                <select name="unidad_edit" id="edit_unidad" style="width: 100%; padding: 8px;">
                    <option value="kg">kg</option><option value="litros">Litros</option><option value="unidades">Unidades</option><option value="gramos">g</option><option value="ml">ml</option>
                </select>
            </div>
            <div class="form-group"><label>Stock Actual:</label><input type="number" step="0.1" name="stock_edit" id="edit_stock" required style="width: 100%; padding: 8px;"></div>
            <div class="form-group"><label>Stock Mínimo:</label><input type="number" step="0.1" name="stock_min_edit" id="edit_stock_min" required style="width: 100%; padding: 8px;"></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" onclick="cerrarModalEditarIng()" class="btn" style="background: #999; flex: 1;">Cancelar</button>
                <button type="submit" name="editar_ingrediente" class="btn" style="background: #32cd32; flex: 1;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- SECCIÓN 2: RECETAS Y PRODUCTOS             -->
<!-- ========================================== -->
<div id="seccion-recetas" style="display: none;">
    
    <!-- Formulario Crear Receta -->
    <div class="form-container" style="max-width: 100%; margin-bottom: 30px;">
        <h3 style="color: var(--rosa-oscuro); margin-top: 0;">Crear / Añadir a Receta</h3>
        <form method="POST" action="inventario.php">
            <div class="form-group" style="max-width: 400px;">
                <label>Selecciona el Producto:</label>
                <select name="producto_id" required style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid var(--rosa-fuerte);">
                    <?php 
                    $productos_array = [];
                    while($p = $lista_productos->fetch_assoc()) {
                        $productos_array[] = $p;
                        echo "<option value='{$p['id']}'>{$p['nombre']}</option>"; 
                    }
                    ?>
                </select>
            </div>
            <div id="contenedor-ingredientes" style="margin-top: 15px; background: #fff8f8; padding: 15px; border-radius: 10px; border: 1px dashed var(--rosa-fuerte);">
                <p style="margin-top:0; font-weight: bold; color: #666;">Ingredientes necesarios para 1 unidad:</p>
                <div class="fila-ingrediente" style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <select name="ingredientes[]" style="flex-grow: 1; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                        <option value="">-- Seleccionar Ingrediente --</option>
                        <?php 
                        $lista_ingredientes_select->data_seek(0);
                        while($i = $lista_ingredientes_select->fetch_assoc()) {
                            echo "<option value='{$i['id']}'>{$i['nombre']} ({$i['unidad_medida']})</option>";
                        }
                        ?>
                    </select>
                    <input type="number" step="0.01" name="cantidades[]" placeholder="Cantidad" style="width: 120px; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="button" onclick="agregarFila()" class="btn" style="background: #8d6e63; flex: 1;">➕ Añadir otro ingrediente</button>
                <button type="submit" name="agregar_recetas_multiples" class="btn" style="background: var(--rosa-oscuro); flex: 2;">Guardar Receta</button>
            </div>
        </form>
    </div>

    <!-- Visualización de Recetas en Tarjetas -->
    <h3 style="color: var(--rosa-oscuro); border-bottom: 2px solid var(--rosa-fuerte); padding-bottom: 10px;">Recetario de La Soleil</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
        <?php
        $query_tarjetas = $conn->query("
            SELECT r.id as id_receta, r.producto_id, p.nombre as producto, i.nombre as ingrediente, i.id as ing_id, r.cantidad_necesaria, i.unidad_medida 
            FROM recetas r 
            JOIN productos p ON r.producto_id = p.id 
            JOIN ingredientes i ON r.ingrediente_id = i.id
            ORDER BY p.nombre ASC
        ");
        
        $recetas_agrupadas = [];
        while($row = $query_tarjetas->fetch_assoc()) {
            $recetas_agrupadas[$row['producto_id']]['nombre'] = $row['producto'];
            $recetas_agrupadas[$row['producto_id']]['ingredientes'][] = $row;
        }

        if (empty($recetas_agrupadas)) echo "<p style='color:#888;'>No hay recetas creadas aún.</p>";

        foreach($recetas_agrupadas as $prod_id => $data):
            $nombre_prod = $data['nombre'];
            $ingredientes_prod = $data['ingredientes'];
            $json_ingredientes = htmlspecialchars(json_encode($ingredientes_prod), ENT_QUOTES, 'UTF-8');
        ?>
            <div class="card" style="padding: 20px; border-top: 5px solid var(--rosa-fuerte); display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: start; border-bottom: 1px dashed #ccc; padding-bottom: 10px; margin-bottom: 10px;">
                    <h3 style="color: var(--rosa-oscuro); margin: 0;">
                        🍰 <?php echo htmlspecialchars($nombre_prod); ?>
                    </h3>
                    <!-- BOTONES EDITAR Y ELIMINAR RECETA COMPLETA -->
                    <div style="display: flex; gap: 5px;">
                        <button onclick='abrirModalEditarReceta(<?php echo $prod_id; ?>, "<?php echo addslashes($nombre_prod); ?>", <?php echo $json_ingredientes; ?>)' class="btn" style="background: #d87093; padding: 4px 8px; font-size: 0.8em;">Editar</button>
                        <a href="inventario.php?eliminar_receta_completa=<?php echo $prod_id; ?>" class="btn" style="background: red; padding: 4px 8px; font-size: 0.8em;" onclick="return confirm('¿Seguro que quieres eliminar TODA la receta de este producto?');">Eliminar</a>
                    </div>
                </div>
                
                <ul style="list-style-type: none; padding: 0; margin: 0; flex-grow: 1;">
                    <?php foreach($ingredientes_prod as $ing): ?>
                        <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.95em;">
                            <!-- Limpio: solo texto y cantidad -->
                            <span>🔸 <?php echo htmlspecialchars($ing['ingrediente']); ?></span>
                            <strong><?php echo number_format($ing['cantidad_necesaria'], 2, ',', '.') . ' ' . $ing['unidad_medida']; ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <hr style="border: 1px solid var(--rosa-fuerte); margin: 40px 0;">

    <!-- Fabricación -->
    <h3 style="color: var(--rosa-oscuro); border-bottom: 2px solid var(--rosa-fuerte); padding-bottom: 10px;">Fabricar / Reponer Productos</h3>
    <table>
        <tr><th>Producto</th><th>Stock Actual</th><th>Añadir Producción</th></tr>
        <?php foreach($productos_array as $pf): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($pf['nombre']); ?></strong></td>
            <td><?php echo $pf['stock']; ?> unidades</td>
            <td>
                <form method="POST" style="display: flex; gap: 5px;">
                    <input type="hidden" name="id_producto" value="<?php echo $pf['id']; ?>">
                    <input type="number" name="cantidad" value="1" min="1" style="width: 60px;">
                    <button type="submit" name="sumar_stock" class="btn" style="background: #32cd32;">+ Fabricar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- MODAL PARA EDITAR RECETA COMPLETA -->
<div id="modalEditarReceta" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 30px; border-radius: 15px; width: 600px; max-width: 90%; border: 3px solid var(--rosa-fuerte);">
        <h3 style="color: var(--rosa-oscuro); margin-top: 0;" id="titulo_receta_edit">Editar Receta</h3>
        
        <form method="POST" action="inventario.php">
            <input type="hidden" name="prod_id_edit" id="edit_receta_prod_id">
            
            <div id="contenedor-ingredientes-edit" style="margin-top: 15px; background: #fff8f8; padding: 15px; border-radius: 10px; border: 1px dashed var(--rosa-fuerte); max-height: 300px; overflow-y: auto;">
                <!-- Las filas se generan con JavaScript aquí -->
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" onclick="agregarFilaEdit()" class="btn" style="background: #8d6e63; flex: 1;">➕ Añadir Ingrediente</button>
                <button type="button" onclick="cerrarModalEditarReceta()" class="btn" style="background: #999; flex: 1;">Cancelar</button>
                <button type="submit" name="actualizar_receta_completa" class="btn" style="background: #32cd32; flex: 2;">Guardar Receta</button>
            </div>
        </form>
    </div>
</div>

<script>
    function mostrarSeccion(seccion) {
        const inv = document.getElementById('seccion-inventario');
        const rec = document.getElementById('seccion-recetas');
        const btnInv = document.getElementById('btn-inventario');
        const btnRec = document.getElementById('btn-recetas');

        if (seccion === 'inventario') {
            inv.style.display = 'block'; rec.style.display = 'none';
            btnInv.style.background = 'var(--rosa-oscuro)'; btnInv.style.color = 'white';
            btnRec.style.background = '#ccc'; btnRec.style.color = '#333';
        } else {
            inv.style.display = 'none'; rec.style.display = 'block';
            btnRec.style.background = 'var(--rosa-oscuro)'; btnRec.style.color = 'white';
            btnInv.style.background = '#ccc'; btnInv.style.color = '#333';
        }
    }

    function agregarFila() {
        const contenedor = document.getElementById('contenedor-ingredientes');
        const nuevaFila = document.createElement('div');
        nuevaFila.className = 'fila-ingrediente';
        nuevaFila.style = 'display: flex; gap: 10px; margin-bottom: 10px;';
        
        const filaBase = contenedor.querySelector('.fila-ingrediente');
        nuevaFila.innerHTML = filaBase.innerHTML;
        
        const btnEliminar = document.createElement('button');
        btnEliminar.type = 'button'; btnEliminar.innerText = 'X';
        btnEliminar.className = 'btn'; btnEliminar.style = 'background: red; padding: 8px 12px;';
        btnEliminar.onclick = function() { nuevaFila.remove(); };
        
        nuevaFila.appendChild(btnEliminar);
        contenedor.appendChild(nuevaFila);
    }

    function abrirModalEditarIng(id, nombre, unidad, stock, stock_min) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nombre').value = nombre;
        document.getElementById('edit_unidad').value = unidad;
        document.getElementById('edit_stock').value = stock;
        document.getElementById('edit_stock_min').value = stock_min;
        document.getElementById('modalEditarIng').style.display = 'flex';
    }
    function cerrarModalEditarIng() {
        document.getElementById('modalEditarIng').style.display = 'none';
    }

    const opcionesIngredientes = `
        <option value="">-- Seleccionar --</option>
        <?php 
        $lista_ingredientes_select->data_seek(0);
        while($i = $lista_ingredientes_select->fetch_assoc()) {
            echo "<option value='{$i['id']}'>{$i['nombre']} ({$i['unidad_medida']})</option>";
        }
        ?>
    `;

    function abrirModalEditarReceta(prod_id, nombre_prod, ingredientes) {
        document.getElementById('edit_receta_prod_id').value = prod_id;
        document.getElementById('titulo_receta_edit').innerText = "Editar Receta: " + nombre_prod;
        
        const contenedor = document.getElementById('contenedor-ingredientes-edit');
        contenedor.innerHTML = ''; 

        if (ingredientes && ingredientes.length > 0) {
            ingredientes.forEach(ing => {
                agregarFilaEdit(ing.ing_id, ing.cantidad_necesaria);
            });
        } else {
            agregarFilaEdit(); 
        }

        document.getElementById('modalEditarReceta').style.display = 'flex';
    }

    function cerrarModalEditarReceta() {
        document.getElementById('modalEditarReceta').style.display = 'none';
    }

    function agregarFilaEdit(ing_id_seleccionado = '', cantidad = '') {
        const contenedor = document.getElementById('contenedor-ingredientes-edit');
        
        const nuevaFila = document.createElement('div');
        nuevaFila.style = 'display: flex; gap: 10px; margin-bottom: 10px;';
        
        const select = document.createElement('select');
        select.name = 'ingredientes_edit[]';
        select.style = 'flex-grow: 1; padding: 8px; border-radius: 5px; border: 1px solid #ccc;';
        select.innerHTML = opcionesIngredientes;
        select.value = ing_id_seleccionado; 

        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.name = 'cantidades_edit[]';
        input.placeholder = 'Cantidad';
        input.value = cantidad;
        input.style = 'width: 120px; padding: 8px; border-radius: 5px; border: 1px solid #ccc;';
        input.required = true;

        const btnEliminar = document.createElement('button');
        btnEliminar.type = 'button'; 
        btnEliminar.innerText = 'X';
        btnEliminar.className = 'btn'; 
        btnEliminar.style = 'background: red; padding: 8px 12px;';
        btnEliminar.onclick = function() { nuevaFila.remove(); };
        
        nuevaFila.appendChild(select);
        nuevaFila.appendChild(input);
        nuevaFila.appendChild(btnEliminar);
        contenedor.appendChild(nuevaFila);
    }
</script>

<?php require 'includes/footer.php'; ?>