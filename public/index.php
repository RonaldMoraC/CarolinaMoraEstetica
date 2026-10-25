<?php
declare(strict_types=1);

/**
 * Front Controller — Carolina Mora Estética
 */

// Iniciar buffer de salida inmediatamente para capturar cualquier salida accidental
ob_start();

// 1. Cargar el sistema y obtener el Router configurado
$router = require __DIR__ . '/../bootstrap.php';

// 2. Despachar la petición actual
$router->dispatch();