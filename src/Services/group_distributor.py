#!/usr/bin/python3

from ortools.sat.python import cp_model
import numpy as np
import argparse
import json

def make_group_weights(groups, split):
    groups_list = []
    for idx, groupNum in enumerate(groups):
        group = {
            'id': idx,
            'group': groupNum,
            'splits': []
        }

        if split:
            splitItem = [int(np.ceil(groupNum / 2)), int(np.floor(groupNum / 2))]
            group['splits'].append(splitItem[0])
            group['splits'].append(splitItem[1])
        else:
            group['splits'].append(groupNum)

        groups_list.append(group)

    return groups_list

def main(groups=[], fields=1, field_capacity=24, split_groups=True):
    data = {}
    data['groups'] = make_group_weights(groups, split_groups and fields > 1)
    data['weights'] = sum([x['splits'] for x in data['groups']], [])
    data['bin_capacities'] = [field_capacity for x in range(fields)]
    data['values'] = np.ones(len(data['weights']))
    data['num_items'] = len(data['weights'])
    data['all_items'] = range(data['num_items'])
    data['num_bins'] = len(data['bin_capacities'])
    data['all_bins'] = range(data['num_bins'])

    # Map unique items to the original group
    itemToGroup = {}
    i = 0
    for group in data['groups']:
        for value in group['splits']:
            for b in data['all_bins']:
                itemToGroup[i, b] = group
            i += 1

    if (fields == 1):
        result = []
        for b in data['all_bins']:
            groups = []
            for i in data['all_items']:
                groups.append({
                    "group_id": itemToGroup[i, b]['id'],
                    "matches": data['weights'][i]
                })

            result.append(groups)

        return { "groups": data['groups'], "distribution": result }

    model = cp_model.CpModel()

    # Variables.
    # x[i, b] = 1 if item i is packed in bin b.
    x = {}
    i = 0
    for group in data['groups']:
        for value in group['splits']:
            for b in data['all_bins']:
                x[i, b] = model.NewBoolVar(f'x_{i}_{b}')
            i += 1

    # Constraints.
    for i in data['all_items']:
        # Each item is assigned to at most one bin.
        model.AddAtMostOne(x[i, b] for b in data['all_bins'])

        # Each item is assigned to at least one bin.
        model.AddAtLeastOne(x[i, b] for b in data['all_bins'])

    # The amount packed in each bin cannot exceed its capacity.
    for b in data['all_bins']:
        model.Add(
            sum(x[i, b] * data['weights'][i]
                for i in data['all_items']) <= data['bin_capacities'][b])

    # Add MSE constraint
    y = {}
    best_fit = int(np.round(sum(groups) / fields))
    for b in data['all_bins']:
        y[b] = model.NewIntVar(0, 1_000_000, f'y_{b}')

        v = model.NewIntVar(-1_000_000, 1_000_000, 'v') # Temporary variable
        model.Add(v == (best_fit - sum(x[i, b] * data['weights'][i] for i in data['all_items'])))

        model.AddMultiplicationEquality(y[b], [
            v,
            v
        ])

    # Objective.
    # Minimize MSE
    objective = []
    for b in data['all_bins']:
        objective.append(cp_model.LinearExpr.Term(y[b], 1))
    model.Minimize(cp_model.LinearExpr.Sum(objective))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 45.0
    status = solver.Solve(model)

    if (status != cp_model.OPTIMAL) and (status != cp_model.FEASIBLE):
        return None

    result = []
    for b in data['all_bins']:
        groups = []
        for i in data['all_items']:
            if solver.Value(x[i, b]) > 0:
                groups.append({
                    "group_id": itemToGroup[i, b]['id'],
                    "matches": data['weights'][i]
                })

        result.append(groups)

    return { "groups": data['groups'], "distribution": result }


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Get best possible distribution of different size elements into x bins')

    parser.add_argument('-g', '--groups',
                    action='append',
                    help='A required array of groups where the element indicates number of matches',
                    required=True)

    parser.add_argument('-f', '--fields',
                    help='A required int of number of fields',
                    required=True)

    parser.add_argument('-c', '--field-capacity',
                    help='An in of the max number og allowed matches per field',
                    default=24)

    parser.add_argument('--disable-group-splitting', action='store_true',
                    help='Disable splitting of groups so e.g. 10 can\'t be split to [5, 5]')

    args = parser.parse_args()

    result = main(
        [int(x) for x in args.groups],
        int(args.fields),
        int(args.field_capacity),
        not args.disable_group_splitting
    )

    json_object = json.dumps(result)
    print(json_object)
