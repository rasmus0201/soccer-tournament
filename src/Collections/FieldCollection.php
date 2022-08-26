<?php

namespace Bunds\Tournament\Collections;

use Bunds\Tournament\Collections\Traits\{SupportsArrayHelpers, SupportsRandomization};
use Bunds\Tournament\Entities\Field;
use Exception;

class FieldCollection extends \ArrayObject
{
    use SupportsRandomization, SupportsArrayHelpers;

    public function __construct(Field ...$fields)
    {
        parent::__construct($fields);
    }

    /** @param Field $value */
    public function append($value): void
    {
        if (!($value instanceof Field)) {
            throw new Exception('Cannot append non-Field to a ' . __CLASS__);
        }

        parent::append($value);
    }

    public function offsetSet($index, $value): void
    {
        if (!($value instanceof Field)) {
            throw new Exception('Cannot append non-Field to a ' . __CLASS__);
        }

        parent::offsetSet($index, $value);
    }
}
