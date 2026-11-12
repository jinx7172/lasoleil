<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'includes/conexion.php';

// 1. Procesar Edición de datos
if (isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $categoria = $_POST['categoria'];
    $stock = $_POST['stock'];
    
    $actual = $conn->query("SELECT imagen FROM productos WHERE id = $id")->fetch_assoc();
    $imagen_nombre = $actual['imagen'];

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $directorio = __DIR__ . "/assets/img/";
        $nombre_archivo = preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['imagen']['name']));
        $imagen_nombre = time() . "_" . $nombre_archivo; 
        move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre);
        if ($actual['imagen'] != 'default.jpg' && file_exists($directorio . $actual['imagen'])) { unlink($directorio . $actual['imagen']); }
    }

    $stmt = $conn->prepare("UPDATE productos SET nombre=?, descripcion=?, precio=?, categoria=?, stock=?, imagen=? WHERE id=?");
    $stmt->bind_param("ssdsisi", $nombre, $descripcion, $precio, $categoria, $stock, $imagen_nombre, $id);
    $stmt->execute();
    $mensaje = "Producto actualizado correctamente.";
}

// 2. Procesar Añadir Existencias (Cantidad Exacta)
if (isset($_POST['accion']) && $_POST['accion'] == 'sumar_stock') {
    $id = $_POST['id_sumar'];
    $cantidad_sumar = (int)$_POST['cantidad_sumar']; 
    
    if ($cantidad_sumar > 0) {
        $conn->query("UPDATE productos SET stock = stock + $cantidad_sumar WHERE id = $id");
        $mensaje = "Se añadieron $cantidad_sumar existencias.";
    }
}

// 3. Procesar Eliminar Producto (NUEVO)
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    
    // Primero obtenemos la imagen para borrarla de la carpeta
    $res = $conn->query("SELECT imagen FROM productos WHERE id = $id_eliminar");
    if ($res->num_rows > 0) {
        $prod = $res->fetch_assoc();
        if ($prod['imagen'] != 'default.jpg' && file_exists(__DIR__ . "/assets/img/" . $prod['imagen'])) {
            unlink(__DIR__ . "/assets/img/" . $prod['imagen']);
        }
    }
    
    // Eliminamos de la base de datos
    $conn->query("DELETE FROM productos WHERE id = $id_eliminar");
    $mensaje = "Producto eliminado del menú.";
}

// 4. Lógica del Buscador en Editar
$categoria_actual = $_GET['cat'] ?? 'Todas';
$busqueda = $_GET['buscar'] ?? '';

$sql = "SELECT * FROM productos WHERE 1=1";
if ($categoria_actual != 'Todas') {
    $sql .= " AND categoria = '$categoria_actual'";
}
if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE '%$busqueda%' OR descripcion LIKE '%$busqueda%')";
}

$resultado = $conn->query($sql);
require 'includes/header.php';
?>

<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: var(--rosa-oscuro);">🛠️ Panel de Administración</h2>
    
    <!-- Botón para volver -->
    <a href="index.php" class="btn" style="background: #8d6e63; margin-bottom: 15px;">Volver al Menú</a>

    <!-- Buscador -->
    <form method="GET" action="editar.php" style="display: inline-block; width: 100%; max-width: 400px; margin-left: 10px;">
        <input type="hidden" name="cat" value="<?php echo $categoria_actual; ?>">
        <div style="display: flex; gap: 10px;">
            <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar producto..." style="flex-grow: 1; padding: 8px; border-radius: 5px; border: 1px solid var(--rosa-fuerte);">
            <button type="submit" class="btn">🔍</button>
        </div>
    </form>
</div>

<!-- Filtros -->
<div class="filtros" style="margin-bottom: 20px;">
    <?php 
    $cats = ['Todas', 'Panes', 'Masitas', 'Galletas', 'Postres', 'Bebidas'];
    foreach($cats as $c) {
        $active = ($categoria_actual == $c) ? 'active' : '';
        echo "<a href='editar.php?cat=$c&buscar=$busqueda' class='active' style='font-size: 0.9em; padding: 5px 10px;'>$c</a>";
    }
    ?>
</div>

<?php if(isset($mensaje)) echo "<p style='color:green; font-weight:bold; text-align:center;'>$mensaje</p>"; ?>

<table>
    <tr><th>Imagen</th><th>Producto</th><th>Precio</th><th>Stock</th><th>Acciones</th></tr>
    <?php while($row = $resultado->fetch_assoc()): ?>
    <tr>
        <td><img src="assets/img/<?php echo htmlspecialchars($row['imagen']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;" onerror="this.src='assets/img/default.jpg'"></td>
        <td><?php echo htmlspecialchars($row['nombre']); ?><br><small><?php echo htmlspecialchars($row['categoria']); ?></small></td>
        <td>Bs <?php echo number_format($row['precio'], 2, ',', '.'); ?></td>
        <td><strong><?php echo $row['stock']; ?></strong></td>
        <td>
            <div style="display: flex; gap: 10px; align-items: center;">
                
                <!-- BOTÓN EDITAR -->
                <a href="editar.php?id=<?php echo $row['id']; ?>" class="btn" style="padding: 5px 10px;">Editar</a>

                <!-- NUEVO BOTÓN ELIMINAR -->
                <a href="editar.php?eliminar=<?php echo $row['id']; ?>" class="btn" style="background: #ff4d4d; padding: 5px 10px;" onclick="return confirm('¿Estás seguro de que quieres eliminar este producto? Se borrará la foto también.');">Eliminar</a>
            </div>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<!-- Formulario de edición (solo aparece si se selecciona un producto) -->
<?php if(isset($_GET['id'])): 
    $id_editar = $_GET['id'];
    $prod = $conn->query("SELECT * FROM productos WHERE id = $id_editar")->fetch_assoc();
    if($prod):
?>
<div class="form-container" style="margin-top: 40px; border-top: 5px solid var(--rosa-fuerte);">
    <h3 style="color: var(--rosa-oscuro); text-align: center;">Editando: <?php echo htmlspecialchars($prod['nombre']); ?></h3>
    
    <form method="POST" action="editar.php" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
        
        <div class="form-group">
            <label>Nombre:</label>
            <input type="text" name="nombre" value="<?php echo htmlspecialchars($prod['nombre']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Descripción:</label>
            <textarea name="descripcion" rows="3" required><?php echo htmlspecialchars($prod['descripcion']); ?></textarea>
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
            <label>Stock Exacto (Reemplaza el actual):</label>
            <input type="number" name="stock" value="<?php echo $prod['stock']; ?>" required>
        </div>
        
        <div class="form-group">
            <label>Cambiar Imagen:</label>
            <input type="file" name="imagen" accept="image/*">
        </div>
        
        <button type="submit" class="btn" style="width: 100%;">Guardar Cambios</button>
    </form>
</div>
<?php endif; endif; ?>

<?php require 'includes/footer.php'; ?>