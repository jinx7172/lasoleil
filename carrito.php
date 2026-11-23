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

<h2 style="color: var(--rosa-oscuro); text-align: center; margin-bottom: 20px;">🛒 Tu Carrito</h2>

<?php if (!empty($_SESSION['carrito'])): ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Producto</th><th>Precio</th><th style="text-align: center;">Cantidad</th><th>Subtotal</th><th style="text-align: center;">Acción</th></tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($_SESSION['carrito'] as $id => $prod): 
                    $subtotal = $prod['precio'] * $prod['cantidad'];
                    $total += $subtotal;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                    <td style="color: var(--rosa-oscuro); font-weight: bold;">Bs <?php echo number_format($prod['precio'], 2, ',', '.'); ?></td>
                    
                    <!-- CONTROLES DE CANTIDAD -->
                    <td style="text-align: center;">
                        <div style="display: inline-flex; align-items: center; gap: 8px; justify-content: center; background: #fff5f8; padding: 4px 10px; border-radius: 20px; border: 1px solid #ffd1dc;">
                            <a href="carrito.php?restar=<?php echo $id; ?>" class="btn" style="padding: 2px 8px; background: #8d6e63; font-size: 1rem; border-radius: 50%; width: 26px; height: 26px; text-decoration: none;">-</a>
                            <strong style="font-size: 1.05rem; min-width: 20px; text-align: center;"><?php echo $prod['cantidad']; ?></strong>
                            <a href="carrito.php?sumar=<?php echo $id; ?>" class="btn" style="padding: 2px 8px; background: #ff69b4; font-size: 1rem; border-radius: 50%; width: 26px; height: 26px; text-decoration: none;">+</a>
                        </div>
                    </td>

                    <td style="font-weight: bold;">Bs <?php echo number_format($subtotal, 2, ',', '.'); ?></td>
                    <td style="text-align: center;">
                        <a href="carrito.php?eliminar=<?php echo $id; ?>" class="btn" style="background:#dc3545; padding: 5px 10px; font-size: 0.85rem;" title="Eliminar del carrito">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="text-align: right; margin-top: 20px; display: flex; flex-direction: column; align-items: flex-end; gap: 15px;">
        <h3 style="color: var(--rosa-oscuro); font-size: 1.4rem;">Total a Pagar: <span style="color: var(--rosa-acento);">Bs <?php echo number_format($total, 2, ',', '.'); ?></span></h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; width: 100%;">
            <a href="index.php" class="btn" style="background: #8d6e63;">⬅️ Seguir Comprando</a>
            <a href="carrito.php?vaciar=true" class="btn" style="background: #dc3545;" onclick="return confirm('¿Estás seguro de vaciar el carrito?');">🗑️ Vaciar Carrito</a>
            <a href="procesar_pago.php" class="btn" style="background: #28a745; font-size: 1.05rem;">💳 Finalizar Compra (PDF)</a>
        </div>
    </div>
<?php else: ?>
    <p style="text-align: center; font-size: 1.2em;">Tu carrito está vacío.</p>
    <div style="text-align: center;">
        <a href="index.php" class="btn">Ver Menú</a>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>