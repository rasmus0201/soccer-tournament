<?php

namespace Bunds\Tournament\Entities;

use Bunds\Tournament\Collections\{GameCollection, TeamCollection};
use JsonSerializable;

class Group implements JsonSerializable
{
    private TeamCollection $teams;
    private GameCollection $games;

    public function __construct(
        public readonly int $index,
        private string $color
    ) {
        $this->teams = new TeamCollection();
        $this->games = new GameCollection();
    }

    public function addTeam(Team $team)
    {
        $this->teams->append($team);
    }

    public function getTeams(): TeamCollection
    {
        return $this->teams;
    }

    public function setTeams(TeamCollection $teams): void
    {
        $this->teams = $teams;
    }

    public function setGames(GameCollection $games)
    {
        $this->games = $games;
    }

    public function getGames(): GameCollection
    {
        return $this->games;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'color' => $this->color,
            'index' => $this->index,
            'teams' => (array) $this->teams,
            'games' => (array) $this->games,
        ];
    }

    public function __clone(): void
    {
        $this->teams = clone $this->teams;
        $this->games = clone $this->games;
    }
}
