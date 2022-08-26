<?php

namespace Bunds\Tournament\Collections;

use Bunds\Tournament\Collections\Traits\SupportsArrayHelpers;
use Bunds\Tournament\Entities\Group;
use Exception;

class GroupCollection extends \ArrayObject
{
    use SupportsArrayHelpers;

    public function __construct(Group ...$groups)
    {
        parent::__construct($groups);
    }

    /** @param Group $value */
    public function append($value): void
    {
        if (!($value instanceof Group)) {
            throw new Exception('Cannot append non-Group to a ' . __CLASS__);
        }

        parent::append($value);
    }

    public function offsetSet($index, $value): void
    {
        if (!($value instanceof Group)) {
            throw new Exception('Cannot append non-Group to a ' . __CLASS__);
        }

        parent::offsetSet($index, $value);
    }
}
