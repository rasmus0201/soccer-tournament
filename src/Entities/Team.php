<?php

namespace Bunds\Tournament\Entities;

use JsonSerializable;
use Stringable;

class Team implements JsonSerializable, Stringable
{
    private ?Group $group;

    public function __construct(
        public readonly string $name,
        public readonly ?string $club = null,
    ) {
        $this->group = null;
    }

    public function setGroup(?Group $group): void
    {
        $this->group = $group;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'name' => $this->name,
            'club' => $this->club,
        ];
    }

    public function __toString(): string
    {
        return "name:{$this->name},club:{$this->club}";
    }

    public function __clone(): void
    {
        if ($this->group) {
            $this->group = clone $this->group;
        }
    }
}
