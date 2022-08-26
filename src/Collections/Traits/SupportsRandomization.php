<?php

namespace Bunds\Tournament\Collections\Traits;

trait SupportsRandomization
{
    public function randomize(): static
    {
        $arr = array_values((array) $this);
        $count = count($arr);

        for ($i = $count - 1; $i >= 0; $i--) {
            // Pick a random index
            // from 0 to i
            $j = rand(0, $i);

            // Swap arr[i] with the
            // element at random index
            $tmp = $arr[$i];
            $arr[$i] = $arr[$j];
            $arr[$j] = $tmp;
        }

        return new static(...$arr);
    }
}
