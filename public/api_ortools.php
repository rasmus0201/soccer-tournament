<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bunds\Tournament\{
    Builders\FieldsBuilder,
    Builders\GamesBuilder,
    Builders\GroupsBuilder,
    Collections\GameCollection,
    Collections\TeamCollection,
    Entities\Team,
    GameScheduler,
    GroupDistributor
};

function sanitize_array(array $array): array
{
    return array_values(
        array_filter(
            array_map(fn (string $s) => trim($s), $array)
        )
    );
}

$colors = [
    '#ff7979',
    '#ffbe76',
    '#22a6b3',
    '#badc58',
    '#686de0',
    '#130f40',
    '#e056fd',
    '#30336b',
    '#f9ca24',
];

$matchTime = intval($_POST['matchTime']);
$startTime = "{$_POST['startTime']}:00";
$endTime = "{$_POST['endTime']}:00";

$teams = sanitize_array(explode("\n", $_POST['teams']));
$numberOfFields = max(1, min((int) $_POST['numberFields'], 10));
$matchDuration = new DateInterval("PT{$matchTime}M");
$start = new DateTime("now {$startTime}");
$end = new DateTime("now {$endTime}");
$gameTimes = iterator_to_array(new DatePeriod($start, $matchDuration, $end));
$fieldCapacity = count($gameTimes);

$groupsBuilder = new GroupsBuilder();

$teams = array_map(function (string $team) {
    $parts = explode(',', $team);

    return new Team($parts[0], $parts[1] ?? null);
}, $teams);
$teams = (new TeamCollection(...$teams))->randomize();

// Can't group/split teams of 1-5
if ($teams->count() < 6) {
    $numberOfFields = 1;
}

$groups = $groupsBuilder->withColors(...$colors)->withTeams($teams)->make();

$gamesBuilder = new GamesBuilder();
$allGames = [];

/** @var \Bunds\Tournament\Entities\Group */
foreach ($groups as $group) {
    $games = $gamesBuilder->withGroup($group, $group->index)->make();
    $group->setGames($games);
    $allGames[] = $games;
}

$allGames = GameCollection::fromCollections(...$allGames);

// No possible solution when number of matches exceeds total number of time slots..
$totalFieldsCapacity = $fieldCapacity * $numberOfFields;
if ($allGames->count() > $totalFieldsCapacity) {
    http_response_code(400);
    echo json_encode([
        'message' => "Ud fra {$teams->count()} antal hold"
            . " og {$groups->count()} antal generede puljer,"
            . " blev der lavet {$allGames->count()} totale antal spil-matches."
            . " Grundet antal mulige spil på alle baner ({$totalFieldsCapacity}) kan der ikke findes en løsning."
            . " Overvej evt at:\n\t- Fjerne hold\n\t- Tilføj flere baner\n\t- Udvid start/slut interval"
    ]);
    exit(0);
}

try {
    $groupDistributor = new GroupDistributor($numberOfFields, $groups);
    $fieldsBuilder = new FieldsBuilder($numberOfFields);
    $fields = $fieldsBuilder->withGroups($groups)->withGroupDistributions(
        $groupDistributor->getBestDistribution($fieldCapacity)
    )->make();

    $gameScheduler = new GameScheduler($fields);
    $orderedFields = $gameScheduler->getBestDistribution();
} catch (\Throwable $th) {
    http_response_code(400);
    echo json_encode([
        'message' => 'Kunne ikke generere puljer eller skema for banerne.. Prøv igen. Evt prøv at ændre på nogle af parametrene.'
    ]);
    exit(0);
}

/** @var \Bunds\Tournament\Entities\Field */
foreach ($orderedFields as $field) {
    /** @var \Bunds\Tournament\Entities\Game */
    foreach ($field->getGames() as $index => $game) {
        $game->setStart(clone $gameTimes[$index]);
    }
}

echo json_encode([
    'groups' => (array) $groups,
    'fields' => (array) $orderedFields
]);
