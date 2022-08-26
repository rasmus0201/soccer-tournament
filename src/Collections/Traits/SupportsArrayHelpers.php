<?php

namespace Bunds\Tournament\Collections\Traits;



trait SupportsArrayHelpers
{
    public function swap(int $aIndex, int $bIndex): static
    {
        $arr = $this->getArrayCopy();
        $tmp = $arr[$aIndex];
        $arr[$aIndex] = $arr[$bIndex];
        $arr[$bIndex] = $tmp;

        return new static(...$arr);
    }

    public function slice(int $offset, int $length, bool $preserve_keys = false): static
    {
        $arr = array_values((array) $this);
        $arr = array_slice($arr, $offset, $length, $preserve_keys);

        return new static(...$arr);
    }

    public function diff(iterable ...$others): static
    {
        $current = $this->getArrayCopy();
        $arr = array_diff($current, ...array_map(fn (iterable $a) => (array) $a, $others));

        return new static(...$arr);
    }

    public function clone(): static
    {
        return new static(
            ...array_map(fn (object $o) => clone $o, (array) $this)
        );
    }
}
