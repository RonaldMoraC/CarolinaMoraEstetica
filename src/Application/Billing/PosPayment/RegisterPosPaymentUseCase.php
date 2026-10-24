<?php

declare(strict_types=1);

namespace App\Application\Billing\PosPayment;

use App\Domain\Billing\Entities\Payment;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Infrastructure\Shared\Errors\Exceptions\NotFoundException;
use App\Domain\Shared\Exceptions\DomainException;

/**
 * Caso de uso: RegisterPosPaymentUseCase
 * Registra un cobro realizado en el POS físico de la sucursal.
 */
final class RegisterPosPaymentUseCase
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly AppointmentRepositoryInterface $appointmentRepository
    ) {}

    public function execute(RegisterPosPaymentDTO $dto): int
    {
        // 1. Validar existencia de la cita
        $appointment = $this->appointmentRepository->findById($dto->appointmentId);
        if (!$appointment) {
            throw new NotFoundException("Cita no encontrada con ID: {$dto->appointmentId}");
        }

        // 2. Validar que no se exceda el monto total (simple control financiero)
        $existingPayments = $this->paymentRepository->findByAppointmentId($dto->appointmentId);
        $totalPaid = 0;
        foreach ($existingPayments as $payment) {
            $totalPaid += $payment->getAmount()->getCents();
        }

        $remainingToPay = ($appointment->getFinalPrice() * 100) - $totalPaid;
        if ($dto->amount->getCents() > $remainingToPay) {
            throw new DomainException("El monto a pagar excede el saldo pendiente de la cita.");
        }

        // 3. Crear el pago
        $payment = new Payment(
            $dto->appointmentId,
            $dto->amount,
            $dto->paymentMethod,
            'SUCCESS', // Pago presencial siempre entra como exitoso
            $dto->transactionReference ?? null
        );

        // 4. Persistir
        return $this->paymentRepository->save($payment);
    }
}
