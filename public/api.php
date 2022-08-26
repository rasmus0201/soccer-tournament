<?php

function sanitize_array(array $array): array
{
    return array_values(
        array_filter(
            array_map(fn (string $s) => trim($s), $array)
        )
    );
}

function dd()
{
    echo '<pre>';
    var_dump(...func_get_args());
    die;
}

$teams = sanitize_array(explode("\n", $_POST['teams']));
shuffle($teams);

$groups = array_fill(0, floor(count($teams) / 4), [
    'teams' => [],
    'matches' => [],
]);

$index = 0;
foreach ($teams as $team) {
    $groups[$index]['teams'][] = $team;

    $index = ($index + 1) % count($groups);
}

foreach ($groups as $groupIndex => $group) {
    $matches = [];
    foreach ($group['teams'] as $teamHome) {
        foreach ($group['teams'] as $teamAway) {
            if ($teamHome === $teamAway) {
                continue;
            }

            $teamNames = [$teamHome, $teamAway];
            sort($teamNames);
            $matchName = implode('-', $teamNames);

            if (isset($matches[$matchName])) {
                continue;
            }

            $matches[$matchName] = [
                $teamHome,
                $teamAway,
            ];
        }
    }

    shuffle($matches);
    $groups[$groupIndex]['matches'] = $matches;
}

echo json_encode($groups);

// Sønderris SK 1
// Bramming B2
// Jerne if 2
// Hjerting IF 2
// Varde IF
// EFB A
// Nr. Bjært/Strandhuse IF
// Årslev boldklub
// NRUI
// SGI
// EFB B
// hfb u 9
// Bjert IF B
// AiF
// Hjerting IF 1
// Dalby GF
// Sønderris SK 2
// Øster Lindet U9 B
// Jerne if 1
// Bramming B1
// Sønderris U9 Piger
// Spangsbjerg IF 2
