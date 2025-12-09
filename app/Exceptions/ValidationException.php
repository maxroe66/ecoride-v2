<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception dédiée aux erreurs de validation multi-champs.
 * Contient la liste des messages d'erreurs et un code HTTP 422 par défaut.
 */
class ValidationException extends \Exception
{
    /** @var array<string> */
    public array $errors;

    /**
     * @param array<string> $errors Liste des erreurs de validation
     * @param int $code Code HTTP, 422 par défaut
     */
    public function __construct(array $errors, int $code = 422)
    {
        parent::__construct('Validation failed', $code);
        $this->errors = $errors;
    }

    /**
     * Retourne la liste détaillée des erreurs
     */
    public function getDetails(): array
    {
        return $this->errors;
    }
}
