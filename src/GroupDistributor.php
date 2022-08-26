<?php

namespace Bunds\Tournament;

use Bunds\Tournament\Collections\GroupCollection;
use Bunds\Tournament\Entities\Group;
use RuntimeException;

class GroupDistributor
{
    public function __construct(private int $fields, private GroupCollection $groups)
    {
    }

    public function getBestDistribution(int $fieldCapacity): array
    {
        $groupsMatchesCount = array_map(fn (Group $g) => count($g->getGames()), (array) $this->groups);
        $groupArgs = '-g ' . implode(' -g ', $groupsMatchesCount);
        $path = __DIR__ . '/Services/group_distributor.py';
        $out = shell_exec("{$path} --fields={$this->fields} {$groupArgs} --field-capacity={$fieldCapacity}");

        if (!is_string($out)) {
            throw new RuntimeException('Could not call group_distributor.py');
        }

        $json = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

        if ($json === null) {
            throw new RuntimeException('Got no distribution');
        }

        return $json['distribution'];
    }
}
