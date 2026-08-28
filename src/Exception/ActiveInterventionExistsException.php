<?php

namespace App\Exception;

/**
 * Levée quand on tente de créer une intervention sur une antenne qui a déjà
 * une intervention active (non clôturée). Traduite en HTTP 409 par
 * l'ExceptionListener.
 */
class ActiveInterventionExistsException extends \DomainException
{
    public function __construct(int $antennaId)
    {
        parent::__construct(sprintf(
            'Antenna #%d already has an active intervention. Close it before creating a new one.',
            $antennaId
        ));
    }
}
