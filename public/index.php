<?php
declare(strict_types=1);

/**
 * Front Controller — Carolina Mora Estética
 */

// 1. Cargar el sistema y obtener el Router configurado
$router = require __DIR__ . '/../bootstrap.php';

// 2. Despachar la petición actual
// El Router se encarga de resolver la URI y ejecutar el controlador o devolver 404/405
$router->dispatch();