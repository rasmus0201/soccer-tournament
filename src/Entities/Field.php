<?php

namespace Bunds\Tournament\Entities;

use Bunds\Tournament\Collections\GameCollection;
use JsonSerializable;

class Field implements JsonSerializable
{
    private GameCollection $games;

    public function __construct(public readonly string $name)
    {
        $this->games = new GameCollection();
    }

    public function addGames(GameCollection $games): void
    {
        foreach ($games as $game) {
            $this->games->append($game);
        }
    }

    public function setGames(GameCollection $games): void
    {
        $this->games = $games;
    }

    public function getGames(): GameCollection
    {
        return $this->games;
    }

    public function swapGames(int $gameIndexA, int $gameIndexB): void
    {
        $this->games = $this->games->swap($gameIndexA, $gameIndexB);
    }

    public function hashGames(): string
    {
        $hash = '';

        /** @var \Bunds\Tournament\Entities\Game */
        foreach ($this->games as $key => $game) {
            $hash .= $key . $game->teamHome->name . $game->teamAway->name;
        }

        return md5($hash);
    }

    public function print(): string
    {
        $str = $this->name;
        $str .= ":\n";

        /** @var \Bunds\Tournament\Entities\Game */
        foreach ($this->games as $index => $game) {
            $str .= "\t{$index}: {$game->teamHome->name} - {$game->teamAway->name}\n";
        }

        $str .= str_repeat('-', 20) . "\n";

        return $str;
    }

    public function jsonSerialize(): array
    {
        return $this->games->getArrayCopy();
    }
}
