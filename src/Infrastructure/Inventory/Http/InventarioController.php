<?php
declare(strict_types=1);

namespace App\Infrastructure\Inventory\Http;

use App\Domain\Inventory\Model\Producto;
use App\Domain\Inventory\Model\MovimientoInventario;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;
use PDO;
use Exception;

class InventarioController
{
    private PDO $db;
    private Producto $productoModel;
    private MovimientoInventario $movimientoModel;

    public function __construct()
    {
        // Conexión centralizada de la base de datos
        $dbPath = __DIR__ . '/../../../../database/connection.php';
        if (file_exists($dbPath)) {
            require_once $dbPath;
            if (function_exists('getDBConnection')) {
                $this->db = getDBConnection();
            } else {
                throw new Exception("Función getDBConnection no disponible.");
            }
        } else {
            // Fallback usando PDO directo si existe config
            $this->db = new PDO("mysql:host=localhost;dbname=carolina_mora_estetica;charset=utf8mb4", "root", "", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }

        $this->productoModel = new Producto($this->db);
        $this->movimientoModel = new MovimientoInventario($this->db);
    }

    public function handle(): void
    {
        $action = $_GET['action'] ?? 'obtener_productos';
        $userRole = $_COOKIE['user_role'] ?? 'CLIENT';
        $isReadOnly = in_array($action, ['obtener_productos', 'obtener_datos_iniciales', 'obtener_movimientos'], true);
        $isWriteAction = in_array($action, ['guardar_producto', 'eliminar_producto', 'registrar_movimiento', 'guardar_proveedor'], true);

        // RBAC: Solo SUPER_ADMIN y MANAGER pueden escribir
        if ($isWriteAction && !in_array($userRole, ['SUPER_ADMIN', 'MANAGER'], true)) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 403,
                type: 'https://carolinamoraestetica.com/errors/forbidden',
                title: 'Acceso Denegado',
                detail: 'No tienes permisos para realizar esta acción.',
                instance: $_SERVER['REQUEST_URI']
            );
            return;
        }

        header('Content-Type: application/json; charset=utf-8');

        try {
            switch ($action) {
                case 'obtener_datos_iniciales':
                    $this->obtenerDatosIniciales();
                    break;
                case 'obtener_productos':
                    $this->obtenerProductos();
                    break;
                case 'guardar_producto':
                    $this->guardarProducto();
                    break;
                case 'eliminar_producto':
                    $this->eliminarProducto();
                    break;
                case 'registrar_movimiento':
                    $this->registrarMovimiento();
                    break;
                case 'obtener_movimientos':
                    $this->obtenerMovimientos();
                    break;
                case 'guardar_proveedor':
                    $this->guardarProveedor();
                    break;
                default:
                    echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
                    break;
            }
        } catch (Exception $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/inventory-error',
                title: 'Error en el Módulo de Inventario',
                detail: $e->getMessage(),
                instance: $_SERVER['REQUEST_URI']
            );
        }
    }

    private function obtenerDatosIniciales(): void
    {
        $categorias = $this->db->query("SELECT id, name FROM product_category ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $marcas = $this->db->query("SELECT id, name FROM product_brand ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $proveedores = $this->db->query("SELECT id, name FROM product_supplier ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'categories' => $categorias,
            'brands' => $marcas,
            'suppliers' => $proveedores
        ]);
    }

    private function obtenerProductos(): void
    {
        $busqueda = $_GET['busqueda'] ?? null;
        $categoriaId = isset($_GET['categoria_id']) && $_GET['categoria_id'] !== '' ? (int)$_GET['categoria_id'] : null;
        $estadoStock = $_GET['estado_stock'] ?? null;

        $productos = $this->productoModel->obtenerTodos($busqueda, $categoriaId, $estadoStock);
        $metricas = $this->productoModel->obtenerMetricas();

        echo json_encode([
            'status' => 'success',
            'data' => $productos,
            'metrics' => $metricas
        ]);
    }

    private function guardarProducto(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $id = isset($input['product_id']) && $input['product_id'] !== '' ? (int)$input['product_id'] : null;
        $nombre = trim($input['name'] ?? '');

        if (empty($nombre)) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre del producto es obligatorio.']);
            return;
        }

        $data = [
            'sku' => trim($input['sku'] ?? ''),
            'name' => $nombre,
            'description' => trim($input['description'] ?? ''),
            'category_id' => !empty($input['category_id']) ? (int)$input['category_id'] : null,
            'brand_id' => !empty($input['brand_id']) ? (int)$input['brand_id'] : null,
            'supplier_id' => !empty($input['supplier_id']) ? (int)$input['supplier_id'] : null,
            'cost_price' => (float)($input['cost_price'] ?? 0),
            'unit_price' => (float)($input['unit_price'] ?? 0),
            'minimum_stock' => (int)($input['minimum_stock'] ?? 5)
        ];

        if ($id) {
            $this->productoModel->actualizar($id, $data);
            echo json_encode(['status' => 'success', 'message' => 'Producto actualizado correctamente.']);
        } else {
            $data['current_stock'] = (int)($input['current_stock'] ?? 0);
            $nuevoId = $this->productoModel->crear($data);

            if ($data['current_stock'] > 0) {
                $this->movimientoModel->registrar($nuevoId, $_SESSION['usuarioSesion']['id'] ?? null, 'ENTRADA_INICIAL', $data['current_stock'], 'Stock inicial al crear producto');
            }

            echo json_encode(['status' => 'success', 'message' => 'Producto creado correctamente.', 'id' => $nuevoId]);
        }
    }

    private function eliminarProducto(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_GET;
        $id = isset($input['id']) ? (int)$input['id'] : 0;

        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de producto inválido.']);
            return;
        }

        $this->productoModel->eliminar($id);
        echo json_encode(['status' => 'success', 'message' => 'Producto eliminado correctamente.']);
    }

    private function registrarMovimiento(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $productoId = (int)($input['product_id'] ?? 0);
        $tipo = trim($input['movement_type'] ?? '');
        $cantidad = (int)($input['quantity'] ?? 0);
        $razon = trim($input['reason'] ?? '');

        if ($productoId <= 0 || empty($tipo) || $cantidad <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Datos de movimiento incompletos o inválidos.']);
            return;
        }

        $producto = $this->productoModel->obtenerPorId($productoId);
        if (!$producto) {
            echo json_encode(['status' => 'error', 'message' => 'Producto no encontrado.']);
            return;
        }

        $stockActual = (int)$producto['current_stock'];
        $nuevoStock = $stockActual;

        if (in_array($tipo, ['ENTRADA_COMPRA', 'AJUSTE'])) {
            $nuevoStock += $cantidad;
        } elseif (in_array($tipo, ['SALIDA_VENTA', 'CONSUMO_INTERNO', 'MERMA'])) {
            $nuevoStock -= $cantidad;
            if ($nuevoStock < 0) {
                echo json_encode(['status' => 'error', 'message' => 'Stock insuficiente para realizar esta salida. Stock actual: ' . $stockActual]);
                return;
            }
        }

        $this->db->beginTransaction();
        try {
            $this->productoModel->actualizarStock($productoId, $nuevoStock);
            $usuarioId = $_SESSION['usuarioSesion']['id'] ?? null;
            $this->movimientoModel->registrar($productoId, $usuarioId, $tipo, $cantidad, $razon);
            $this->db->commit();

            echo json_encode(['status' => 'success', 'message' => 'Movimiento registrado correctamente. Stock actual: ' . $nuevoStock]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function obtenerMovimientos(): void
    {
        $movimientos = $this->movimientoModel->obtenerHistorial(100);
        echo json_encode(['status' => 'success', 'data' => $movimientos]);
    }

    private function guardarProveedor(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $nombre = trim($input['supplier_name'] ?? '');

        if (empty($nombre)) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre del proveedor es obligatorio.']);
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO product_supplier (name, contact_name, phone, email, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $nombre,
            trim($input['contact_name'] ?? ''),
            trim($input['phone'] ?? ''),
            trim($input['email'] ?? '')
        ]);

        $nuevoId = (int)$this->db->lastInsertId();
        echo json_encode(['status' => 'success', 'message' => 'Proveedor creado correctamente.', 'id' => $nuevoId]);
    }
}
