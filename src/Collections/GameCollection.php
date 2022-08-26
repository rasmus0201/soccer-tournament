<?php

namespace Bunds\Tournament\Collections;

use ArrayObject;
use Bunds\Tournament\Collections\Traits\{SupportsArrayHelpers, SupportsRandomization};
use Bunds\Tournament\Entities\Game;
use Exception;

class GameCollection extends ArrayObject
{
    use SupportsRandomization, SupportsArrayHelpers;

    public function __construct(Game ...$games)
    {
        parent::__construct($games);
    }

    /** @param Game $value */
    public function append($value): void
    {
        if (!($value instanceof Game)) {
            throw new Exception('Cannot append ' . get_class($value) . ' to a ' . __CLASS__);
        }

        parent::append($value);
    }

    public function offsetSet($index, $value): void
    {
        if (!($value instanceof Game)) {
            throw new Exception('Cannot append ' . get_class($value) . ' to a ' . __CLASS__);
        }

        parent::offsetSet($index, $value);
    }

    public static function fromCollections(GameCollection ...$collections): static
    {
        $merged = new static();

        foreach ($collections as $collection) {
            foreach ($collection as $item) {
                $merged->append($item);
            }
        };

        return $merged;
    }
}
