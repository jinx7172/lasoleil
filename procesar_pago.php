<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Procesando pedido...</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .loader-container { text-align: center; margin-top: 50px; }
        
        /* Tamaño para el GIF caminando */
        .gato-gif {
            width: 300px; /* Ajusta este valor si el GIF se ve muy chico o grande */
            height: auto;
            margin-bottom: 20px;
        }

        /* Tamaño para el PNG sentado */
        .gato-png {
            width: 220px; /* Ajusta este valor si el PNG se ve muy chico o grande */
            height: auto;
            margin-bottom: 20px;
        }

        /* Animación para el GIF mientras camina */
        .animacion-caminar {
            animation: mover 2s infinite alternate;
        }
        @keyframes mover {
            from { transform: translateX(-10px); }
            to { transform: translateX(10px); }
        }

        .oculto { display: none; }
        .aparecer { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body style="background-color: var(--rosa-pastel);">
    <div class="loader-container">
        
        <!-- GIF de caminando (visible al inicio) -->
        <img src="assets/img/gato_caminando.gif" alt="Gatito caminando" class="gato-gif animacion-caminar" id="gifGato">
        
        <!-- PNG estático (oculto al inicio) -->
        <img src="assets/img/gato_final.png" alt="Gatito feliz" class="gato-png oculto" id="pngGato">
        
        <h2 style="color: var(--rosa-oscuro);" id="tituloMensaje">¡Preparando tu pedido en La Soleil!</h2>
        <p id="textoMensaje">Tu factura se está generando y descargando...</p>

        <div id="contenedorBoton" class="oculto" style="margin-top: 30px;">
            <a href="index.php" class="btn" style="font-size: 1.2em; padding: 15px 30px;">Volver al Menú Principal</a>
        </div>
    </div>

    <!-- El iframe descarga el PDF en segundo plano -->
    <iframe src="factura.php" style="display: none;"></iframe>

    <script>
        setTimeout(function() {
            // 1. Cambiamos los textos
            document.getElementById('tituloMensaje').innerText = "¡Factura generada con éxito!";
            document.getElementById('textoMensaje').innerText = "Tu pedido está listo. ¡Gracias por comprar en La Soleil!";
            
            // 2. Ocultamos el GIF y mostramos el PNG
            document.getElementById('gifGato').classList.add('oculto');
            
            const pngGato = document.getElementById('pngGato');
            pngGato.classList.remove('oculto');
            pngGato.classList.add('aparecer');
            
            // 3. Mostramos el botón
            const contenedorBoton = document.getElementById('contenedorBoton');
            contenedorBoton.classList.remove('oculto');
            contenedorBoton.classList.add('aparecer');
        }, 3000); 
    </script>
</body>
</html>