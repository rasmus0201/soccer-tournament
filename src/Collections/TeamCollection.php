<?php

namespace Bunds\Tournament\Collections;

use Bunds\Tournament\Collections\Traits\{SupportsArrayHelpers, SupportsRandomization};
use Bunds\Tournament\Entities\Team;
use Exception;

class TeamCollection extends \ArrayObject
{
    use SupportsRandomization, SupportsArrayHelpers;

    public function __construct(Team ...$teams)
    {
        parent::__construct($teams);
    }

    /** @param Team $value */
    public function append($value): void
    {
        if (!($value instanceof Team)) {
            throw new Exception('Cannot append non-Team to a ' . __CLASS__);
        }

        parent::append($value);
    }

    public function offsetSet($index, $value): void
    {
        if (!($value instanceof Team)) {
            throw new Exception('Cannot append non-Team to a ' . __CLASS__);
        }

        parent::offsetSet($index, $value);
    }
}
