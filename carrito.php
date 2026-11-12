<?php
require 'includes/conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Lógica para SUMAR cantidad en el carrito
if (isset($_GET['sumar'])) {
    $id = $_GET['sumar'];
    $res = $conn->query("SELECT stock FROM productos WHERE id = $id");
    $prod = $res->fetch_assoc();
    
    // Solo sumamos si no superamos el stock
    if ($_SESSION['carrito'][$id]['cantidad'] < $prod['stock']) {
        $_SESSION['carrito'][$id]['cantidad']++;
    }
    header("Location: carrito.php");
    exit;
}

// Lógica para RESTAR cantidad en el carrito
if (isset($_GET['restar'])) {
    $id = $_GET['restar'];
    if ($_SESSION['carrito'][$id]['cantidad'] > 1) {
        $_SESSION['carrito'][$id]['cantidad']--;
    } else {
        // Si llega a 0, lo eliminamos
        unset($_SESSION['carrito'][$id]);
    }
    header("Location: carrito.php");
    exit;
}

// Lógica para ELIMINAR producto
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    unset($_SESSION['carrito'][$id]);
    header("Location: carrito.php");
    exit;
}

// Lógica para VACIAR carrito
if (isset($_GET['vaciar'])) {
    unset($_SESSION['carrito']);
    header("Location: carrito.php");
    exit;
}

require 'includes/header.php';
?>

<h2 style="color: var(--rosa-oscuro);">🛒 Tu Carrito</h2>

<?php if (!empty($_SESSION['carrito'])): ?>
    <table>
        <tr><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th><th>Acción</th></tr>
        <?php 
        $total = 0;
        foreach ($_SESSION['carrito'] as $id => $prod): 
            $subtotal = $prod['precio'] * $prod['cantidad'];
            $total += $subtotal;
        ?>
        <tr>
            <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
            <td>Bs <?php echo number_format($prod['precio'], 2, ',', '.'); ?></td>
            
            <!-- CONTROLES DE CANTIDAD -->
            <td>
                <div style="display: flex; align-items: center; gap: 10px; justify-content: center;">
                    <a href="carrito.php?restar=<?php echo $id; ?>" class="btn" style="padding: 2px 10px; background: #8d6e63; font-size: 1.2em; border-radius: 50%;">-</a>
                    <strong style="font-size: 1.1em;"><?php echo $prod['cantidad']; ?></strong>
                    <a href="carrito.php?sumar=<?php echo $id; ?>" class="btn" style="padding: 2px 10px; background: #ff69b4; font-size: 1.2em; border-radius: 50%;">+</a>
                </div>
            </td>

            <td>Bs <?php echo number_format($subtotal, 2, ',', '.'); ?></td>
            <td><a href="carrito.php?eliminar=<?php echo $id; ?>" class="btn" style="background:red; padding: 5px 10px;">X</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <div style="text-align: right; margin-top: 20px;">
        <h3>Total: Bs <?php echo number_format($total, 2, ',', '.'); ?></h3>
        <a href="index.php" class="btn" style="background: #8d6e63;">Seguir Comprando</a>
        <a href="carrito.php?vaciar=true" class="btn" style="background: red;">Vaciar Carrito</a>
        <a href="procesar_pago.php" class="btn" style="background: #32cd32;">Finalizar Compra (PDF)</a>
    </div>
<?php else: ?>
    <p style="text-align: center; font-size: 1.2em;">Tu carrito está vacío.</p>
    <div style="text-align: center;">
        <a href="index.php" class="btn">Ver Menú</a>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>