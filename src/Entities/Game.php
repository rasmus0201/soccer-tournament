<?php

namespace Bunds\Tournament\Entities;

use DateTime;
use JsonSerializable;
use Stringable;

class Game implements Stringable, JsonSerializable
{
    private ?DateTime $start;

    public function __construct(
        public readonly int $groupIndex,
        public readonly Team $teamHome,
        public readonly Team $teamAway
    ) {
        $this->start = null;
    }

    public function setStart(?DateTime $start): void
    {
        $this->start = $start;
    }

    public function __toString(): string
    {
        return spl_object_hash($this);
    }

    public function print(): string
    {
        return $this->teamHome->name . ' - ' . $this->teamAway->name;
    }

    public function jsonSerialize(): array
    {
        return [
            'group' => $this->groupIndex,
            'teams' => [
                $this->teamHome->name,
                $this->teamAway->name,
            ],
            'start' => [
                'time' => $this->start?->format('H:i'),
            ],
        ];
    }

    public function __clone(): void
    {
        $this->teamHome = clone $this->teamHome;
        $this->teamAway = clone $this->teamAway;

        if ($this->start) {
            $this->start = clone $this->start;
        }
    }
}
