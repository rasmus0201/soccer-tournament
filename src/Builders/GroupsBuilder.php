<?php

namespace Bunds\Tournament\Builders;

use Bunds\Tournament\Collections\{GroupCollection, TeamCollection};
use Bunds\Tournament\Entities\Group;
use LogicException;

class GroupsBuilder
{
    private TeamCollection $teams;
    /** @var string[] */
    private array $colors;

    public function __construct(
        private int $maxTeamsPerGroup = 5,
        private string $defaultColor = '#ff0000'
    ) {
        $this->colors = [$defaultColor];
    }

    public function withTeams(TeamCollection $teams): self
    {
        $this->teams = $teams;

        return $this;
    }

    public function withColors(string ...$colors): self
    {
        $this->colors = $colors;

        return $this;
    }

    public function make(): GroupCollection
    {
        // 0. Make sure all input is set correctly.
        $this->inputGuard();

        // 1. Create the groups
        $groupCount = ceil(count($this->teams) / $this->maxTeamsPerGroup);
        /** @var Group[] */
        $groups = [];
        for ($i = 0; $i < $groupCount; $i++) {
            $color = array_shift($this->colors) ?? $this->defaultColor;
            $groups[] = new Group($i, $color);
        }

        // 2. Map teams to their clubs (if no team it gets a tmp team)
        $teamsByClub = [];
        /** @var \Bunds\Tournament\Entities\Team */
        foreach ($this->teams as $team) {
            $club = $team->club !== null ? $team->club : $team->name;

            if (!isset($teamsByClub[$club])) {
                $teamsByClub[$club] = [];
            }

            $teamsByClub[$club][] = $team;
        }

        // 3. Assign each team a group, trying to avoid putting teams from same club into same group.
        $clubKeys = array_keys($teamsByClub);
        $fullyMappedClubKeys = [];
        $groupClubCountMap = array_fill_keys(range(0, $groupCount - 1), array_fill_keys($clubKeys, 0));
        while (count($fullyMappedClubKeys) < count($clubKeys)) {
            $clubIndex = current($clubKeys);

            if (!isset($fullyMappedClubKeys[$clubIndex])) {
                // Get a team
                $teamIndex = array_key_first($teamsByClub[$clubIndex]);
                $team = array_splice($teamsByClub[$clubIndex], $teamIndex, 1);

                // If we exhausted the club's teams, we need to store that
                // to avoid trying to take more teams from there.
                if (count($teamsByClub[$clubIndex]) === 0) {
                    $fullyMappedClubKeys[$clubIndex] = true;
                }

                if ($team) {
                    // Calculate the possible groups this team
                    // could be assigned.
                    $possibleGroups = [];
                    foreach ($groupClubCountMap as $groupIndex => $clubCounts) {
                        $teamCount = $groups[$groupIndex]->getTeams()->count();
                        if ($teamCount >= $this->maxTeamsPerGroup) {
                            continue;
                        }

                        // By multiplying the amount of teams from the club
                        // with how many teams are in the group, we can assign group
                        // both by the amount of teams from the same club _but also_
                        // making sure we assign teams equally between all groups.
                        // (the 1's are added to make sure to not cancel any terms)
                        $possibleGroups[$groupIndex] = (1 + $clubCounts[$clubIndex]) * (1 + $teamCount);
                    }

                    // Sort the possible groups,
                    // the group with the least "score" (e.g. amount of same team-clubs and group-teams)
                    // will be the assigned group the for current team.
                    asort($possibleGroups, SORT_NUMERIC);
                    $groupIndex = array_key_first($possibleGroups);


                    // Set this a reference to the group on the team
                    $team[0]->setGroup($groups[$groupIndex]);

                    $groups[$groupIndex]->addTeam($team[0]);
                    $groupClubCountMap[$groupIndex][$clubIndex] += 1;
                }
            }

            // Make sure for each iteration, we advance the array pointer,
            // resetting if we have hit the last element.
            if ($clubIndex === $clubKeys[array_key_last($clubKeys)]) {
                reset($clubKeys);
            } else {
                next($clubKeys);
            }
        }

        // 4. Make group collection and return.
        return new GroupCollection(...$groups);
    }

    private function inputGuard(): void
    {
        if (!isset($this->teams) || $this->teams->count() === 0) {
            throw new LogicException('Teams must be set and non-empty');
        }

        if ($this->maxTeamsPerGroup <= 0) {
            throw new LogicException('maxTeamsPerGroup must be >= 1');
        }
    }
}
