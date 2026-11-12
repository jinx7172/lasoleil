<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'includes/conexion.php';

// --- LÓGICA PARA AÑADIR NUEVO INGREDIENTE ---
if (isset($_POST['nuevo_ingrediente'])) {
    $nombre = $_POST['nombre_ing'];
    $unidad = $_POST['unidad'];
    $stock_inicial = (float)$_POST['stock_inicial'];
    $stock_minimo = (float)$_POST['stock_minimo'];
    $proveedor = !empty($_POST['proveedor_id']) ? $_POST['proveedor_id'] : "NULL";

    // Si el proveedor está vacío, lo insertamos como NULL
    if ($proveedor === "NULL") {
        $conn->query("INSERT INTO ingredientes (nombre, stock_actual, unidad_medida, stock_minimo) VALUES ('$nombre', $stock_inicial, '$unidad', $stock_minimo)");
    } else {
        $conn->query("INSERT INTO ingredientes (nombre, stock_actual, unidad_medida, stock_minimo, proveedor_id) VALUES ('$nombre', $stock_inicial, '$unidad', $stock_minimo, $proveedor)");
    }
    $mensaje = "¡Ingrediente '$nombre' registrado con éxito!";
}

// --- LÓGICA PARA AÑADIR RECETAS (MÚLTIPLES) ---
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
        if ($agregados > 0) {
            $mensaje_receta = "Se agregaron $agregados ingredientes a la receta.";
        }
    }
}

// --- LÓGICA PARA SUMAR STOCK DE PRODUCTO (Y DESCONTAR INGREDIENTES) ---
if (isset($_POST['sumar_stock'])) {
    $id_prod = $_POST['id_producto'];
    $cantidad_prod = (int)$_POST['cantidad'];
    
    if ($cantidad_prod > 0) {
        $conn->query("UPDATE productos SET stock = stock + $cantidad_prod WHERE id = $id_prod");
        $receta_query = $conn->query("SELECT ingrediente_id, cantidad_necesaria FROM recetas WHERE producto_id = $id_prod");
        
        $ingredientes_descontados = 0;
        while ($ing = $receta_query->fetch_assoc()) {
            $ing_id = $ing['ingrediente_id'];
            $cant_necesaria = $ing['cantidad_necesaria'] * $cantidad_prod; 
            $conn->query("UPDATE ingredientes SET stock_actual = stock_actual - $cant_necesaria WHERE id = $ing_id");
            $ingredientes_descontados++;
        }
        
        if ($ingredientes_descontados > 0) {
            $mensaje = "Se fabricaron $cantidad_prod productos y se descontaron los ingredientes.";
        } else {
            $mensaje = "Se sumaron $cantidad_prod productos (Sin receta asignada).";
        }
    }
}

// --- LÓGICA PARA SUMAR STOCK DIRECTO A UN INGREDIENTE ---
if (isset($_POST['sumar_ingrediente'])) {
    $id_ing = $_POST['id_ingrediente'];
    $cantidad_ing = (float)$_POST['cantidad_ing'];
    if ($cantidad_ing > 0) {
        $conn->query("UPDATE ingredientes SET stock_actual = stock_actual + $cantidad_ing WHERE id = $id_ing");
        $mensaje = "Stock de ingrediente actualizado.";
    }
}

// Variables para las tablas
$ingredientes = $conn->query("
    SELECT i.*, p.nombre_empresa as proveedor 
    FROM ingredientes i 
    LEFT JOIN proveedores p ON i.proveedor_id = p.id
    ORDER BY i.nombre ASC
");
$lista_productos = $conn->query("SELECT * FROM productos ORDER BY nombre ASC");
$lista_ingredientes_select = $conn->query("SELECT * FROM ingredientes ORDER BY nombre ASC");
$lista_proveedores = $conn->query("SELECT * FROM proveedores ORDER BY nombre_empresa ASC");

require 'includes/header.php';
?>

<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: var(--rosa-oscuro);">🥣 Inventario y Recetas</h2>
    <a href="index.php" class="btn" style="background: #8d6e63; margin-bottom: 15px;">Volver al Menú</a>
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
    
    <!-- FORMULARIO NUEVO INGREDIENTE -->
    <div class="form-container" style="max-width: 100%; margin-bottom: 30px; border-left: 5px solid #32cd32;">
        <h3 style="color: var(--rosa-oscuro); margin-top: 0;">➕ Registrar Nuevo Ingrediente</h3>
        <form method="POST" action="inventario.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
            
            <div class="form-group" style="flex: 2; min-width: 200px; margin-bottom: 0;">
                <label>Nombre del Ingrediente:</label>
                <input type="text" name="nombre_ing" placeholder="Ej: Cacao en polvo" required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 120px; margin-bottom: 0;">
                <label>Unidad:</label>
                <select name="unidad" required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="kg">Kilogramos (kg)</option>
                    <option value="litros">Litros (L)</option>
                    <option value="unidades">Unidades</option>
                    <option value="gramos">Gramos (g)</option>
                    <option value="ml">Mililitros (ml)</option>
                </select>
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 100px; margin-bottom: 0;">
                <label>Stock Inicial:</label>
                <input type="number" step="0.1" name="stock_inicial" value="0" required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
            </div>

            <div class="form-group" style="flex: 1; min-width: 100px; margin-bottom: 0;">
                <label>Alerta Mínimo:</label>
                <input type="number" step="0.1" name="stock_minimo" value="5" required style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
            </div>

            <div class="form-group" style="flex: 1.5; min-width: 150px; margin-bottom: 0;">
                <label>Proveedor (Opcional):</label>
                <select name="proveedor_id" style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="">-- Ninguno --</option>
                    <?php while($prov = $lista_proveedores->fetch_assoc()): ?>
                        <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre_empresa']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" name="nuevo_ingrediente" class="btn" style="background: #32cd32; padding: 9px 20px; height: 38px;">Guardar</button>
        </form>
    </div>

    <h3 style="color: var(--rosa-oscuro); border-bottom: 2px solid var(--rosa-fuerte); padding-bottom: 10px;">Stock Actual de Ingredientes</h3>
    <table>
        <tr><th>Ingrediente</th><th>Proveedor</th><th>Stock Actual</th><th>Estado</th><th>Añadir Stock</th></tr>
        <?php while($ing = $ingredientes->fetch_assoc()): 
            $alerta = ($ing['stock_actual'] <= $ing['stock_minimo']);
        ?>
        <tr style="<?php echo $alerta ? 'background:#ffe6e6;' : ''; ?>">
            <td><strong><?php echo htmlspecialchars($ing['nombre']); ?></strong></td>
            <td><?php echo $ing['proveedor'] ? htmlspecialchars($ing['proveedor']) : 'Sin asignar'; ?></td>
            <td style="color: <?php echo $alerta ? '#ff4d4d' : '#32cd32'; ?>; font-weight: bold; font-size: 1.1em;">
                <?php echo number_format($ing['stock_actual'], 2, ',', '.') . ' ' . $ing['unidad_medida']; ?>
            </td>
            <td><?php echo $alerta ? '<span style="color:red; font-weight:bold;">¡Bajo!</span>' : 'OK'; ?></td>
            <td>
                <form method="POST" style="display: flex; gap: 5px;">
                    <input type="hidden" name="id_ingrediente" value="<?php echo $ing['id']; ?>">
                    <input type="number" step="0.1" name="cantidad_ing" value="10" style="width: 60px;">
                    <button type="submit" name="sumar_ingrediente" class="btn" style="background: #32cd32;">+</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<!-- ========================================== -->
<!-- SECCIÓN 2: RECETAS Y PRODUCTOS             -->
<!-- ========================================== -->
<div id="seccion-recetas" style="display: none;">
    
    <!-- Formulario Dinámico para añadir varios ingredientes -->
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

    <!-- VISUALIZACIÓN DE RECETAS EN TARJETAS -->
    <h3 style="color: var(--rosa-oscuro); border-bottom: 2px solid var(--rosa-fuerte); padding-bottom: 10px;">Recetario de La Soleil</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
        <?php
        $query_tarjetas = $conn->query("
            SELECT p.nombre as producto, i.nombre as ingrediente, r.cantidad_necesaria, i.unidad_medida 
            FROM recetas r 
            JOIN productos p ON r.producto_id = p.id 
            JOIN ingredientes i ON r.ingrediente_id = i.id
            ORDER BY p.nombre ASC
        ");
        
        $recetas_agrupadas = [];
        while($row = $query_tarjetas->fetch_assoc()) {
            $recetas_agrupadas[$row['producto']][] = $row;
        }

        if (empty($recetas_agrupadas)) {
            echo "<p style='color:#888;'>No hay recetas creadas aún.</p>";
        }

        foreach($recetas_agrupadas as $nombre_prod => $ingredientes_prod):
        ?>
            <div class="card" style="padding: 20px; border-top: 5px solid var(--rosa-fuerte);">
                <h3 style="color: var(--rosa-oscuro); margin-top: 0; border-bottom: 1px dashed #ccc; padding-bottom: 10px;">
                    🍰 <?php echo htmlspecialchars($nombre_prod); ?>
                </h3>
                <ul style="list-style-type: none; padding: 0; margin: 0;">
                    <?php foreach($ingredientes_prod as $ing): ?>
                        <li style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.95em;">
                            <span>🔸 <?php echo htmlspecialchars($ing['ingrediente']); ?></span>
                            <strong><?php echo number_format($ing['cantidad_necesaria'], 2, ',', '.') . ' ' . $ing['unidad_medida']; ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <hr style="border: 1px solid var(--rosa-fuerte); margin: 40px 0;">

    <!-- FABRICACIÓN -->
    <h3 style="color: var(--rosa-oscuro); border-bottom: 2px solid var(--rosa-fuerte); padding-bottom: 10px;">Fabricar / Reponer Productos</h3>
    <p style="color: #666; font-size: 0.9em;">Al sumar stock aquí, el sistema descontará automáticamente los ingredientes de la receta.</p>
    
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

<!-- SCRIPT PARA PESTAÑAS Y FILAS DINÁMICAS -->
<script>
    function mostrarSeccion(seccion) {
        const inv = document.getElementById('seccion-inventario');
        const rec = document.getElementById('seccion-recetas');
        const btnInv = document.getElementById('btn-inventario');
        const btnRec = document.getElementById('btn-recetas');

        if (seccion === 'inventario') {
            inv.style.display = 'block';
            rec.style.display = 'none';
            btnInv.style.background = 'var(--rosa-oscuro)';
            btnInv.style.color = 'white';
            btnRec.style.background = '#ccc';
            btnRec.style.color = '#333';
        } else {
            inv.style.display = 'none';
            rec.style.display = 'block';
            btnRec.style.background = 'var(--rosa-oscuro)';
            btnRec.style.color = 'white';
            btnInv.style.background = '#ccc';
            btnInv.style.color = '#333';
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
        btnEliminar.type = 'button';
        btnEliminar.innerText = 'X';
        btnEliminar.className = 'btn';
        btnEliminar.style = 'background: red; padding: 8px 12px;';
        btnEliminar.onclick = function() { nuevaFila.remove(); };
        
        nuevaFila.appendChild(btnEliminar);
        contenedor.appendChild(nuevaFila);
    }
</script>

<?php require 'includes/footer.php'; ?>