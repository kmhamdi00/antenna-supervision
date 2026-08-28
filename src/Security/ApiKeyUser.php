<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Utilisateur "technique" minimal représentant un appelant authentifié par
 * clé API.
 */
class ApiKeyUser implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_API'];
    }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return 'api-client';
    }
}
