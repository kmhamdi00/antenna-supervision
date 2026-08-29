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

#[AsEventListener(event: 'kernel.exception', priority: 0)]
class ApiExceptionListener
{
    public function __construct(private readonly bool $debug) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
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

        if ($this->debug && Response::HTTP_INTERNAL_SERVER_ERROR === $status) {
            $payload['error']['debug'] = [
                'exception' => get_class($exception),
                'real_message' => $exception->getMessage(),
                'file' => $exception->getFile() . ':' . $exception->getLine(),
            ];
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
