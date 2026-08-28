<?php

namespace App\Exception;

/**
 * Levée quand on tente de clôturer une intervention déjà clôturée.
 * Traduite en HTTP 409 par l'ExceptionListener.
 */
class InterventionAlreadyClosedException extends \DomainException
{
    public function __construct(int $interventionId)
    {
        parent::__construct(sprintf('Intervention #%d is already closed.', $interventionId));
    }
}
