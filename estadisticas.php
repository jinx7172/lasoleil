<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'includes/conexion.php';

// --- CONSULTAS PARA LAS TARJETAS DE RESUMEN ---

// 1. Ventas de Hoy
$hoy = date('Y-m-d');
$res_hoy = $conn->query("SELECT SUM(total) as total, COUNT(id) as transacciones FROM ventas WHERE DATE(fecha) = '$hoy'");
$ventas_hoy = $res_hoy->fetch_assoc();

// 2. Ventas de esta semana (Últimos 7 días)
$res_semana = $conn->query("SELECT SUM(total) as total FROM ventas WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$ventas_semana = $res_semana->fetch_assoc();

// 3. Ventas de este mes
$mes_actual = date('Y-m');
$res_mes = $conn->query("SELECT SUM(total) as total FROM ventas WHERE DATE_FORMAT(fecha, '%Y-%m') = '$mes_actual'");
$ventas_mes = $res_mes->fetch_assoc();

// --- CONSULTAS PARA PRODUCTOS MÁS VENDIDOS ---

// Top 5 de siempre (Histórico)
$top_productos = $conn->query("
    SELECT p.nombre, SUM(v.cantidad) as total_vendido 
    FROM ventas v 
    JOIN productos p ON v.producto_id = p.id 
    GROUP BY v.producto_id 
    ORDER BY total_vendido DESC LIMIT 5
");

// Producto más vendido del DÍA
$top_dia = $conn->query("
    SELECT p.nombre, SUM(v.cantidad) as total_vendido 
    FROM ventas v 
    JOIN productos p ON v.producto_id = p.id 
    WHERE DATE(v.fecha) = '$hoy'
    GROUP BY v.producto_id 
    ORDER BY total_vendido DESC LIMIT 1
")->fetch_assoc();

require 'includes/header.php';
?>

<div style="text-align: center; margin-bottom: 30px;">
    <h2 style="color: var(--rosa-oscuro);">📊 Estadísticas de La Soleil</h2>
</div>

<!-- TARJETAS DE RESUMEN -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    
    <!-- Tarjeta Hoy -->
    <div class="card" style="padding: 20px; text-align: center; border-left: 5px solid #ff69b4;">
        <h3 style="margin: 0; color: #666; font-size: 1em;">Ventas de Hoy</h3>
        <p style="font-size: 2em; font-weight: bold; color: #ff69b4; margin: 10px 0;">Bs <?php echo number_format($ventas_hoy['total'] ?? 0, 2, ',', '.'); ?></p>
        <small style="color: #888;"><?php echo $ventas_hoy['transacciones'] ?? 0; ?> pedidos realizados</small>
    </div>

    <!-- Tarjeta Semana -->
    <div class="card" style="padding: 20px; text-align: center; border-left: 5px solid #d87093;">
        <h3 style="margin: 0; color: #666; font-size: 1em;">Últimos 7 días</h3>
        <p style="font-size: 2em; font-weight: bold; color: #d87093; margin: 10px 0;">Bs <?php echo number_format($ventas_semana['total'] ?? 0, 2, ',', '.'); ?></p>
    </div>

    <!-- Tarjeta Mes -->
    <div class="card" style="padding: 20px; text-align: center; border-left: 5px solid #8d6e63;">
        <h3 style="margin: 0; color: #666; font-size: 1em;">Este Mes</h3>
        <p style="font-size: 2em; font-weight: bold; color: #8d6e63; margin: 10px 0;">Bs <?php echo number_format($ventas_mes['total'] ?? 0, 2, ',', '.'); ?></p>
    </div>

</div>

<!-- PRODUCTO ESTRELLA DEL DÍA -->
<div style="background: #fff0f5; border: 2px dashed #ffb6c1; border-radius: 15px; padding: 20px; text-align: center; margin-bottom: 30px;">
    <h3 style="color: var(--rosa-oscuro); margin-top: 0;">⭐ Producto Estrella de Hoy</h3>
    <?php if($top_dia): ?>
        <p style="font-size: 1.3em; font-weight: bold; color: #5d4037;">
            🏆 <?php echo htmlspecialchars($top_dia['nombre']); ?> 
            <span style="font-size: 0.8em; font-weight: normal; color: #888;">(<?php echo $top_dia['total_vendido']; ?> vendidos hoy)</span>
        </p>
    <?php else: ?>
        <p style="color: #888;">Aún no hay ventas registradas hoy.</p>
    <?php endif; ?>
</div>

<!-- TABLA DE LOS MÁS VENDIDOS (TOP 5) -->
<h3 style="color: var(--rosa-oscuro); border-bottom: 2px solid #ffb6c1; padding-bottom: 10px;">🔥 Top 5 Más Vendidos (Histórico)</h3>
<table style="margin-top: 20px;">
    <tr>
        <th>Puesto</th>
        <th>Producto</th>
        <th>Cantidad Vendida</th>
    </tr>
    <?php 
    $puesto = 1;
    if ($top_productos && $top_productos->num_rows > 0):
        while($row = $top_productos->fetch_assoc()): 
    ?>
    <tr>
        <td><strong>#<?php echo $puesto++; ?></strong></td>
        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
        <td><?php echo $row['total_vendido']; ?> unidades</td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="3" style="text-align: center;">Aún no hay ventas registradas.</td></tr>
    <?php endif; ?>
</table>

<?php require 'includes/footer.php'; ?>