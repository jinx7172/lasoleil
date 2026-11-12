<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require('fpdf/fpdf.php');

if (empty($_SESSION['carrito'])) {
    die("No hay productos en el carrito.");
}

require('includes/conexion.php');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// --- ENCABEZADO ESTILO NEKOPARA ---
$pdf->SetFillColor(255, 228, 225); 
$pdf->Rect(0, 0, 210, 50, 'F'); // Hacemos el fondo un poco más alto

// 1. LOGO MÁS GRANDE (Ajustamos el tamaño a 45 y lo bajamos un poco)
$ruta_logo = __DIR__ . '/assets/img/logo.png';
if (file_exists($ruta_logo)) {
    $pdf->Image($ruta_logo, 5, 3, 75); 
}

// Título
$pdf->SetFont('Helvetica', 'B', 26); // Letra más grande
$pdf->SetTextColor(216, 112, 147); 
$pdf->SetY(15);
$pdf->Cell(0, 10, 'La Soleil', 0, 1, 'C');

// Subtítulo
$pdf->SetFont('Helvetica', 'I', 12);
$pdf->SetTextColor(93, 64, 55); 
$pdf->Cell(0, 10, 'Panaderia y Reposteria', 0, 1, 'C');

// --- DATOS DE LA EMPRESA Y FACTURA ---
$pdf->SetY(60);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, 'La Soleil S.R.L.', 0, 1, 'L');
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(100, 100, 100); // Texto gris para los datos secundarios
$pdf->Cell(0, 5, 'Avenida América, entre calle Potosí y calle Pando', 0, 1, 'L');
$pdf->Cell(0, 5, '+591 700 000 01 | NIT: 1020304050', 0, 1, 'L');
$pdf->Cell(0, 5, 'contacto@lasoleil.com | www.lasoleil.com', 0, 1, 'L');

// Cuadro de la Factura
$pdf->SetY(60);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor(216, 112, 147);
$pdf->Cell(0, 6, 'FACTURA N°: 00' . rand(1000, 9999), 0, 1, 'R'); // Código aleatorio
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, 'Fecha: ' . date('d/m/Y H:i'), 0, 1, 'R');
$pdf->Cell(0, 5, 'Método de Pago: Efectivo', 0, 1, 'R');

$pdf->Ln(10);

// --- TABLA DE PRODUCTOS ---
$pdf->SetFillColor(255, 182, 193); 
$pdf->SetTextColor(255, 255, 255); 
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(85, 10, 'Producto', 0, 0, 'C', true);
$pdf->Cell(30, 10, 'Precio', 0, 0, 'C', true);
$pdf->Cell(20, 10, 'Cant.', 0, 0, 'C', true);
$pdf->Cell(45, 10, 'Subtotal', 0, 0, 'C', true);
$pdf->Ln();

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Helvetica', '', 11);
$fill = false; 
$total = 0;

foreach ($_SESSION['carrito'] as $id => $prod) {
    $subtotal = $prod['precio'] * $prod['cantidad'];
    $total += $subtotal;
    
    $pdf->SetFillColor(250, 250, 250); 
    $pdf->Cell(85, 10, ' ' . $prod['nombre'], 0, 0, 'L', $fill);
    $pdf->Cell(30, 10, 'Bs ' . number_format($prod['precio'], 2, ',', '.'), 0, 0, 'C', $fill);
    $pdf->Cell(20, 10, $prod['cantidad'], 0, 0, 'C', $fill);
    $pdf->Cell(45, 10, 'Bs ' . number_format($subtotal, 2, ',', '.'), 0, 1, 'C', $fill);
    
    $fill = !$fill; 
    
    // 1. Actualizar Stock
    $cantidad = $prod['cantidad'];
    $conn->query("UPDATE productos SET stock = stock - $cantidad WHERE id = $id");

    // 2. GUARDAR LA VENTA EN EL HISTORIAL (NUEVO)
    $total_venta_item = $prod['precio'] * $prod['cantidad'];
    $conn->query("INSERT INTO ventas (producto_id, cantidad, total) VALUES ($id, $cantidad, $total_venta_item)");
}

// --- TOTAL ---
$pdf->Ln(5);
$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetTextColor(216, 112, 147); 
$pdf->Cell(135, 10, 'TOTAL A PAGAR:', 0, 0, 'R');
$pdf->Cell(45, 10, 'Bs ' . number_format($total, 2, ',', '.'), 0, 1, 'C');

// --- MENSAJE DE AGRADECIMIENTO Y SELLO ---
$pdf->Ln(15);
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->SetTextColor(93, 64, 55); 
$pdf->Cell(0, 8, '¡Gracias por endulzar tu día con nosotros!', 0, 1, 'C');

$pdf->SetFont('Helvetica', 'I', 10);
$pdf->SetTextColor(150, 150, 150);
$pdf->Cell(0, 6, 'Cada bocado está preparado con amor (y un toque de vainilla).', 0, 1, 'C');
$pdf->Cell(0, 6, '¡Esperamos verte pronto en La Soleil!', 0, 1, 'C');

// Simulamos un "Sello" de la tienda
$pdf->Ln(10);
$pdf->SetDrawColor(216, 112, 147); // Color del borde del sello
$pdf->SetLineWidth(0.5);
$pdf->SetFillColor(255, 240, 245); // Fondo rosa clarito
$pdf->SetTextColor(216, 112, 147);
$pdf->SetFont('Helvetica', 'B', 12);

// Vaciamos el carrito
unset($_SESSION['carrito']);

// Generamos el PDF
$pdf->Output('D', 'Factura_LaSoleil.pdf'); 
?>