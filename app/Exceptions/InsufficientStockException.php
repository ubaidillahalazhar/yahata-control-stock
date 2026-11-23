<?php

namespace App\Exceptions;

use App\Models\ToolsInventory;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly ToolsInventory $tool,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(sprintf(
            'Stok "%s" tidak mencukupi. Stok sistem: %d pcs, dibutuhkan: %d pcs.',
            $tool->tools_name_yes,
            $available,
            $requested,
        ));
    }

    public function toValidationException(string $field = 'qty'): ValidationException
    {
        return ValidationException::withMessages([$field => $this->getMessage()]);
    }
}
