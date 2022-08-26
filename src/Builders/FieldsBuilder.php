<?php

namespace Bunds\Tournament\Builders;

use Bunds\Tournament\Collections\{FieldCollection, GroupCollection};
use Bunds\Tournament\Entities\Field;
use LogicException;
use RuntimeException;

class FieldsBuilder
{
    private GroupCollection $groups;
    private array $groupDistributions;

    public function __construct(private int $noFields)
    {
    }

    public function withGroups(GroupCollection $groups): self
    {
        $this->groups = $groups;

        return $this;
    }

    /** @param array $groupDistributions result from GroupDistributor class */
    public function withGroupDistributions(array $groupDistributions): self
    {
        $this->groupDistributions = $groupDistributions;

        return $this;
    }

    public function make(): FieldCollection
    {
        // 0. Make sure all input is set correctly.
        $this->inputGuard();

        // 1. Copy groups, to make sure we don't alter in the original.
        $groups = $this->groups->clone();

        // 2. Randomize the games, to try and randomize what games will be put in the fields
        //    (assuming the group was 'split', otherwise it will not have an effect.)
        /** @var \Bunds\Tournament\Entities\Group */
        foreach ($groups as $group) {
            $group->setGames($group->getGames()->randomize());
        }

        // 3. Create the fields array.
        /** @var Field[] */
        $fields = array_map(fn (int $i) => new Field("Field #{$i}"), range(1, $this->noFields));

        // 4. Assign matches given group<->field distribution.
        foreach ($this->groupDistributions as $fieldIndex => $distributions) {
            foreach ($distributions as $distribution) {
                /** @var \Bunds\Tournament\Entities\Group */
                $group = $groups[$distribution['group_id']];

                // Slice the number of matches and set the remaining games
                $games = $group->getGames()->slice(0, $distribution['matches']);
                $group->setGames($group->getGames()->diff($games));

                $fields[$fieldIndex]->addGames($games);
            }
        }

        // 5. Check for missing games to be assigned - if so the must be an error.
        /** @var \Bunds\Tournament\Entities\Group */
        foreach ($groups as $group) {
            if ($group->getGames()->count() > 0) {
                throw new RuntimeException('Expected all group games to be assigned, but still found unassigned games.');
            }
        }


        // 6. Set the matches on the groups again to track group games.
        /** @var \Bunds\Tournament\Entities\Group */
        foreach ($groups as $index => $group) {
            $group->setGames($this->groups[$index]->getGames());
        }

        return new FieldCollection(...$fields);
    }

    private function inputGuard(): void
    {
        if (!isset($this->groups) || $this->groups->count() === 0) {
            throw new LogicException('Groups must be set and non-empty.');
        }

        if (!isset($this->groupDistributions) || count($this->groupDistributions) === 0) {
            throw new LogicException('"groupDistributions" must be set and non-empty.');
        }
    }
}
