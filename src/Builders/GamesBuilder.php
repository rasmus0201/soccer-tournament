<?php

namespace Bunds\Tournament\Builders;

use Bunds\Tournament\Collections\GameCollection;
use Bunds\Tournament\Entities\{Game, Group};

class GamesBuilder
{
    private Group $group;
    private int $groupIndex;

    public function withGroup(Group $group, int $index): self
    {
        $this->group = $group;
        $this->groupIndex = $index;

        return $this;
    }

    public function make(): GameCollection
    {
        $games = [];
        /** @var \Bunds\Tournament\Entities\Team */
        foreach ($this->group->getTeams() as $teamHomeIndex => $teamHome) {
            /** @var \Bunds\Tournament\Entities\Team */
            foreach ($this->group->getTeams() as $teamAwayIndex => $teamAway) {
                if ($teamHome === $teamAway) {
                    continue;
                }

                $teams = [$teamHomeIndex, $teamAwayIndex];
                sort($teams);
                $gameName = implode('-', $teams);

                if (isset($games[$gameName])) {
                    continue;
                }

                $games[$gameName] = new Game(
                    $this->groupIndex,
                    $teamHome,
                    $teamAway,
                );
            }
        }

        $games = array_values($games);

        return new GameCollection(...$games);
    }
}
