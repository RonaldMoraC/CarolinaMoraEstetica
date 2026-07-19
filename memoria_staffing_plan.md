Plan de desarrollo y ejecución para Staffing y Agenda

Objetivo: completar el módulo de Staffing y agenda para que quede funcional de forma end-to-end, con reglas de negocio reales, disponibilidad de horarios, excepciones, control de concurrencia y soporte para operaciones de agenda.

Prioridad 1: base sólida de Staffing
1. Completar entidades de dominio
   - WorkSchedule: validación de horas, día de semana, almuerzo y composición de jornada.
   - ScheduleException: validación de motivos y rangos válidos.
   - ProfessionalProfile: estado operativo y reglas básicas.

2. Completar casos de uso de aplicación
   - ConfigureScheduleUseCase: guardar/reescribir mallas semanales del profesional.
   - CreateScheduleExceptionUseCase: crear bloqueos de agenda con validación de solapamiento.
   - GetAvailableSlotsUseCase: calcular ranuras libres considerando malla, excepciones y citas existentes.

3. Completar repositorio de persistencia
   - Implementar el flujo real de lectura/escritura de work_schedule y schedule_exception.
   - Añadir consultas útiles para disponibilidad y solapamiento.

4. Completar controladores HTTP
   - SaveScheduleController
   - GetSlotsController
   - ScheduleExceptionController
   - GetProfessionalsController

5. Integración con booking
   - Asegurar que la creación de citas consulte disponibilidad real de staffing antes de insertar.
   - Evitar overbooking en el mismo professional/branch/rango.

6. Pruebas
   - Unit tests para WorkSchedule, ScheduleException y GetAvailableSlotsUseCase.
   - Integration tests para flujo mínimo de agenda.

Entregables
- Malla semanal configurada y persistida.
- Excepciones de agenda registradas.
- Disponibilidad calculada correctamente por día/hora.
- Citas creadas solo si hay espacio disponible.
- Reglas de negocio para cancelación/reprogramación/check-in/completion operando con base real.
