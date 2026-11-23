<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require('fpdf/fpdf.php');

if (empty($_SESSION['carrito'])) {
    die("No hay productos en el carrito.");
}

require('includes/conexion.php');

// Función para corregir codificación UTF-8 a ISO-8859-1 / Windows-1252 para FPDF
function fix_texto($str) {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'windows-1252', 'UTF-8');
    } elseif (function_exists('iconv')) {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $str);
    }
    return utf8_decode($str);
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// --- ENCABEZADO ---
$pdf->SetFillColor(255, 228, 225); 
$pdf->Rect(0, 0, 210, 58, 'F'); 

$ruta_logo = __DIR__ . '/assets/img/logo.png';
if (file_exists($ruta_logo)) {
    // Logo más grande y prominente
    $pdf->Image($ruta_logo, 6, 2.5, 100); 
}

// Título
$pdf->SetFont('Helvetica', 'B', 28);
$pdf->SetTextColor(216, 112, 147); 
$pdf->SetY(17);
$pdf->Cell(0, 11, fix_texto('La Soleil'), 0, 1, 'C');

// Subtítulo
$pdf->SetFont('Helvetica', 'I', 13);
$pdf->SetTextColor(93, 64, 55); 
$pdf->Cell(0, 8, fix_texto('Panadería y Repostería'), 0, 1, 'C');

// --- DATOS DE LA EMPRESA Y FACTURA ---
$pdf->SetY(66);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, fix_texto('La Soleil S.R.L.'), 0, 1, 'L');
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, fix_texto('Avenida América, entre calle Potosí y calle Pando'), 0, 1, 'L');
$pdf->Cell(0, 5, fix_texto('+591 700 000 01 | NIT: 1020304050'), 0, 1, 'L');
$pdf->Cell(0, 5, fix_texto('contacto@lasoleil.com | www.lasoleil.com'), 0, 1, 'L');

// Cuadro de la Factura
$pdf->SetY(66);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetTextColor(216, 112, 147);
$pdf->Cell(0, 6, fix_texto('FACTURA N°: 00' . rand(1000, 9999)), 0, 1, 'R');
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, fix_texto('Fecha: ' . date('d/m/Y H:i')), 0, 1, 'R');
$pdf->Cell(0, 5, fix_texto('Método de Pago: Efectivo'), 0, 1, 'R');

$pdf->Ln(10);

// --- TABLA DE PRODUCTOS ---
$pdf->SetFillColor(255, 182, 193); 
$pdf->SetTextColor(255, 255, 255); 
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(85, 10, fix_texto('Producto'), 0, 0, 'C', true);
$pdf->Cell(30, 10, fix_texto('Precio'), 0, 0, 'C', true);
$pdf->Cell(20, 10, fix_texto('Cant.'), 0, 0, 'C', true);
$pdf->Cell(45, 10, fix_texto('Subtotal'), 0, 0, 'C', true);
$pdf->Ln();

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Helvetica', '', 11);
$fill = false; 
$total = 0;

foreach ($_SESSION['carrito'] as $id => $prod) {
    $subtotal = $prod['precio'] * $prod['cantidad'];
    $total += $subtotal;
    
    $pdf->SetFillColor(250, 250, 250); 
    $pdf->Cell(85, 10, ' ' . fix_texto($prod['nombre']), 0, 0, 'L', $fill);
    $pdf->Cell(30, 10, 'Bs ' . number_format($prod['precio'], 2, ',', '.'), 0, 0, 'C', $fill);
    $pdf->Cell(20, 10, $prod['cantidad'], 0, 0, 'C', $fill);
    $pdf->Cell(45, 10, 'Bs ' . number_format($subtotal, 2, ',', '.'), 0, 1, 'C', $fill);
    
    $fill = !$fill; 
    
    // 1. Actualizar Stock
    $cantidad = $prod['cantidad'];
    $conn->query("UPDATE productos SET stock = stock - $cantidad WHERE id = $id");

    // 2. GUARDAR LA VENTA EN EL HISTORIAL
    $total_venta_item = $prod['precio'] * $prod['cantidad'];
    $conn->query("INSERT INTO ventas (producto_id, cantidad, total) VALUES ($id, $cantidad, $total_venta_item)");
}

// --- TOTAL ---
$pdf->Ln(5);
$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetTextColor(216, 112, 147); 
$pdf->Cell(135, 10, fix_texto('TOTAL A PAGAR:'), 0, 0, 'R');
$pdf->Cell(45, 10, 'Bs ' . number_format($total, 2, ',', '.'), 0, 1, 'C');

// --- MENSAJE DE AGRADECIMIENTO Y SELLO ---
$pdf->Ln(15);
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->SetTextColor(93, 64, 55); 
$pdf->Cell(0, 8, fix_texto('¡Gracias por endulzar tu día con nosotros!'), 0, 1, 'C');

$pdf->SetFont('Helvetica', 'I', 10);
$pdf->SetTextColor(150, 150, 150);
$pdf->Cell(0, 6, fix_texto('Cada bocado está preparado con amor (y un toque de vainilla).'), 0, 1, 'C');
$pdf->Cell(0, 6, fix_texto('¡Esperamos verte pronto en La Soleil!'), 0, 1, 'C');

// Vaciamos el carrito
unset($_SESSION['carrito']);

// Generamos el PDF
$pdf->Output('D', 'Factura_LaSoleil.pdf'); 
?>
