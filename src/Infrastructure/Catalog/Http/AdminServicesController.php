<?php
declare(strict_types=1);

namespace App\Infrastructure\Catalog\Http;

use App\Infrastructure\Catalog\Persistence\PdoServiceRepository;
use App\Infrastructure\Shared\Errors\GlobalExceptionHandler;

class AdminServicesController
{
    private PdoServiceRepository $serviceRepository;

    public function __construct(PdoServiceRepository $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    public function getAll(): void
    {
        try {
            // Obtener TODOS los servicios, incluyendo los inactivos (para el panel de administración)
            $services = $this->serviceRepository->findAll();
            
            // Formatear respuesta según RFC 7807
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => $services,
                'meta' => [
                    'total_records' => count($services)
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            GlobalExceptionHandler::emitRfc7807Response(
                httpStatus: 500,
                type: 'https://carolinamoraestetica.com/errors/services-fetch-failed',
                title: 'Error al recuperar servicios',
                detail: 'No se pudieron obtener los servicios desde la base de datos.',
                instance: '/api/v1/admin/services'
            );
        }
    }
}