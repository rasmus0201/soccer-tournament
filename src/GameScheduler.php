<?php

namespace Bunds\Tournament;

use Bunds\Tournament\Collections\{FieldCollection, GameCollection};
use Bunds\Tournament\Entities\{Field, Game};
use RuntimeException;

class GameScheduler
{
    public function __construct(private FieldCollection $fields)
    {
    }

    public function getBestDistribution(): FieldCollection
    {
        /** @var array<int, \Bunds\Tournament\Entities\Team> */
        $teamsById = [];

        /** @var array<string, int> */
        $teamIdsByTeam = [];

        /** @var Field[] */
        $fields = array_fill(0, $this->fields->count(), []);

        $teamId = 0;
        /** @var \Bunds\Tournament\Entities\Field */
        foreach ($this->fields as $index => $field) {
            /** @var \Bunds\Tournament\Entities\Game */
            foreach ($field->getGames() as $game) {
                if (!isset($teamIdsByTeam[(string) $game->teamHome])) {
                    $teamIdsByTeam[(string) $game->teamHome] = $teamId;
                    $teamsById[$teamId] = $game->teamHome;

                    $teamId += 1;
                }

                if (!isset($teamIdsByTeam[(string) $game->teamAway])) {
                    $teamIdsByTeam[(string) $game->teamAway] = $teamId;
                    $teamsById[$teamId] = $game->teamAway;

                    $teamId += 1;
                }

                $fields[$index][] = [
                    $teamIdsByTeam[(string) $game->teamHome],
                    $teamIdsByTeam[(string) $game->teamAway],
                ];
            }
        }

        $dataArg = escapeshellarg(json_encode($fields));

        $path = __DIR__ . '/Services/game_scheduler.py';
        $out = shell_exec("{$path} --data={$dataArg}");

        if (!is_string($out)) {
            throw new RuntimeException('Could not call game_scheduler.py');
        }

        $json = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

        if ($json === null) {
            throw new RuntimeException('Got no schedule');
        }

        $fields = new FieldCollection();

        foreach ($json as $fieldIndex => $fieldGames) {
            $field = new Field($this->fields[$fieldIndex]->name);

            /** @var Game[] */
            $games = [];
            foreach ($fieldGames as $game) {
                [$teamHomeIndex, $teamAwayIndex] = $game;

                $teamHome = $teamsById[$teamHomeIndex];
                $teamAway = $teamsById[$teamAwayIndex];

                $games[] = new Game($teamHome->getGroup()->index, $teamHome, $teamAway);
            }

            $field->setGames(new GameCollection(...$games));
            $fields->append($field);
        }

        if ($this->checkDoubleBookings($fields)) {
            throw new RuntimeException('Schedule contains double booked team(s)!');
        }

        return $fields;
    }

    public function getDistribution(): FieldCollection
    {
        // 1. Clone to make sure we don't overwrite anything in original object.
        $fields = clone $this->fields;

        /** @var \Bunds\Tournament\Entities\Field */
        foreach ($fields as $field) {
            $fieldGames = $field->getGames();
            $fieldGamesCount = $fieldGames->count();

            $gamesByHomeTeam = $this->getGamesByHomeTeamCount($fieldGames);
            $orderedGames = new GameCollection(...array_merge(...array_values($gamesByHomeTeam)));

            $orderedGamesCount = 0;
            $currentIndex = 0;
            $oldTeam = null;
            $teamOffset = 0;
            $gameOffset = 0;
            $occupiedIndexes = [];
            $firstGameIndexByTeam = [];
            while ($orderedGamesCount < $fieldGamesCount && $currentIndex < $fieldGamesCount) {
                $team = $orderedGames[$currentIndex]->teamHome->name;

                if ($team !== $oldTeam) {
                    $firstGameIndexByTeam[$team] = $currentIndex;
                    $teamOffset = intval(round($fieldGamesCount / count($gamesByHomeTeam[$team])));
                    $gameOffset = 0;
                    $oldTeam = $team;
                }

                if ($gameOffset === 0) {
                    $occupiedIndexes[$currentIndex] = true;

                    // echo "i: {$currentIndex}, s: {$currentIndex}, to: {$teamOffset}, t: {$team}\n";

                    $currentIndex += 1;
                    $gameOffset += 1;

                    continue;
                }

                if (isset($occupiedIndexes[$currentIndex])) {
                    $currentIndex += 1;
                    $gameOffset = 0;

                    continue;
                }

                $swapIndex = (1 + $firstGameIndexByTeam[$team] + ($gameOffset * $teamOffset)) % $fieldGamesCount;
                if (isset($occupiedIndexes[$swapIndex])) {
                    while (isset($occupiedIndexes[$swapIndex])) {
                        $swapIndex = ($swapIndex + 1) % $fieldGamesCount;
                    }
                }

                $orderedGames = $orderedGames->swap($currentIndex, $swapIndex);
                $occupiedIndexes[$swapIndex] = true;

                // echo "i: {$currentIndex}, go: {$gameOffset}, to: {$teamOffset}, s: {$swapIndex}, t: {$team}\n";

                $currentIndex += 1;
                $gameOffset += 1;
            }

            $field->setGames($orderedGames);
        }

        $exploredCombinations = [];
        while ($doubleBookings = $this->checkDoubleBookings($fields)) {
            foreach ($doubleBookings as $doubleBooking) {
                [$fieldIndex, $gameIndex] = $doubleBooking;

                $fieldCount = $fields[$fieldIndex]->getGames()->count();
                for ($i = 1; $i <= $fieldCount; $i++) {
                    $newIndex = ($gameIndex + $i) % $fields[$fieldIndex]->getGames()->count();
                    $originalField = clone $fields[$fieldIndex];
                    $fields[$fieldIndex]->setGames(
                        $fields[$fieldIndex]->getGames()->swap($gameIndex, $newIndex)
                    );

                    $hash = $fields[$fieldIndex]->hashGames();
                    if (isset($exploredCombinations[$fieldIndex][$hash])) {
                        $fields[$fieldIndex] = $originalField;
                        continue;
                    }

                    $exploredCombinations[$fieldIndex][$hash] = true;
                    break 2;
                }
            }
        }

        return $fields;
    }

    private function getGamesByHomeTeamCount(GameCollection $games): array
    {
        $gamesByHomeTeam = [];
        foreach ($games as $game) {
            if (!isset($gamesByHomeTeam[$game->teamHome->name])) {
                $gamesByHomeTeam[$game->teamHome->name] = [];
            }

            $gamesByHomeTeam[$game->teamHome->name][] = $game;
        }

        uasort($gamesByHomeTeam, fn (array $a, array $b) => count($b) <=> count($a));

        return $gamesByHomeTeam;
    }

    private function checkDoubleBookings(FieldCollection $fields): bool|array
    {
        if ($fields->count() <= 1) {
            return false;
        }

        $gameIndexByTeams = [];
        /** @var \Bunds\Tournament\Entities\Field */
        foreach ($fields as $fieldIndex => $field) {
            /** @var \Bunds\Tournament\Entities\Game */
            foreach ($field->getGames() as $index => $game) {
                $homeTeam = $game->teamHome->name;
                $awayTeam = $game->teamAway->name;

                if (!isset($gameIndexByTeams[$homeTeam][$fieldIndex])) {
                    $gameIndexByTeams[$homeTeam][$fieldIndex] = [];
                }

                if (!isset($gameIndexByTeams[$awayTeam][$fieldIndex])) {
                    $gameIndexByTeams[$awayTeam][$fieldIndex] = [];
                }

                $gameIndexByTeams[$awayTeam][$fieldIndex][] = $index;
                $gameIndexByTeams[$homeTeam][$fieldIndex][] = $index;
            }
        }

        foreach ($gameIndexByTeams as $gameIndexes) {
            $combinedFieldGameIndexes = array_merge(...array_values($gameIndexes));

            if (count($combinedFieldGameIndexes) === count(array_unique($combinedFieldGameIndexes))) {
                continue;
            }

            foreach ($gameIndexes as $fieldAIndex => $indexesA) {
                foreach ($gameIndexes as $fieldBIndex => $indexesB) {
                    foreach ($indexesA as $indexA) {
                        foreach ($indexesB as $indexB) {
                            if ($indexA === $indexB) {
                                return [[$fieldAIndex, $indexA], [$fieldBIndex, $indexB]];
                            }
                        }
                    }
                }
            }
        }

        return false;
    }
}
