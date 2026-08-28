<?php

namespace App\EventListener;

use App\Exception\ActiveInterventionExistsException;
use App\Exception\InterventionAlreadyClosedException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Formate toutes les erreurs des routes /api en JSON homogène :
 *
 *   { "error": { "code": "...", "message": "...", "details": [...] } }
 *
 * plutôt que de laisser Symfony renvoyer sa page d'erreur HTML par défaut.
 */
#[AsEventListener(event: 'kernel.exception', priority: 0)]
class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return; // laisse le comportement par défaut (page HTML) pour le dashboard
        }

        $exception = $event->getThrowable();

        [$status, $code, $message, $details] = match (true) {
            $exception instanceof ValidationFailedException => [
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'VALIDATION_ERROR',
                'The request payload is invalid.',
                $this->formatViolations($exception),
            ],
            $exception instanceof ActiveInterventionExistsException => [
                Response::HTTP_CONFLICT,
                'ACTIVE_INTERVENTION_EXISTS',
                $exception->getMessage(),
                [],
            ],
            $exception instanceof InterventionAlreadyClosedException => [
                Response::HTTP_CONFLICT,
                'INTERVENTION_ALREADY_CLOSED',
                $exception->getMessage(),
                [],
            ],
            $exception instanceof UniqueConstraintViolationException => [
                Response::HTTP_CONFLICT,
                'ACTIVE_INTERVENTION_EXISTS',
                'This antenna already has an active intervention.',
                [],
            ],
            $exception instanceof OptimisticLockException => [
                Response::HTTP_CONFLICT,
                'CONCURRENT_MODIFICATION',
                'This resource was modified concurrently, please retry.',
                [],
            ],
            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(),
                'HTTP_ERROR',
                $exception->getMessage(),
                [],
            ],
            default => [
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'INTERNAL_ERROR',
                'An unexpected error occurred.',
                [],
            ],
        };

        $payload = ['error' => ['code' => $code, 'message' => $message]];
        if ([] !== $details) {
            $payload['error']['details'] = $details;
        }

        $event->setResponse(new JsonResponse($payload, $status));
    }

    private function formatViolations(ValidationFailedException $exception): array
    {
        $details = [];
        foreach ($exception->getViolations() as $violation) {
            $details[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $details;
    }
}
