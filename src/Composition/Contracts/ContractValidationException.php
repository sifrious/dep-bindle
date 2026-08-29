<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Composition\Contracts;

use InvalidArgumentException;

final class ContractValidationException extends InvalidArgumentException
{
    /** @param list<string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
