#!/usr/bin/python3

from ortools.sat.python import cp_model
import numpy as np
import argparse
import json

def main(team_games=[]):
    model = cp_model.CpModel()

    teams_by_field = {}
    for field, matches in enumerate(team_games):
        teams = set()
        for home, away in matches:
            teams.add(home)
            teams.add(away)

        teams_by_field[field] = list(teams)

    teams_by_field_matches = {}
    for field, matches in enumerate(team_games):
        for match, teams in enumerate(matches):
            teams_by_field_matches[(field, match)] = teams

    match_by_team_pairs = {}
    for field, matches in enumerate(team_games):
        for match, teams in enumerate(matches):
            match_by_team_pairs[(teams[0], teams[1])] = match

    matches_by_teams = {}
    for field, matches in enumerate(team_games):
        for match, teams in enumerate(matches):
            if teams[0] not in matches_by_teams:
                matches_by_teams[teams[0]] = []
            if teams[1] not in matches_by_teams:
                matches_by_teams[teams[1]] = []

            matches_by_teams[teams[0]].append((field, match))
            matches_by_teams[teams[1]].append((field, match))

    field_by_slot = {}
    for field, matches in enumerate(team_games):
        for slot in range(len(matches)):
            if slot not in field_by_slot:
                field_by_slot[slot] = []

            field_by_slot[slot].append(field)

    game_slots = {}  # = 1 if match is to be scheduled on field f in slot s
    for field, matches in enumerate(team_games):
        for slot in range(len(matches)):
            for match, teams in enumerate(matches):
                game_slots[(field, slot, match)] = model.NewBoolVar(
                    f'game_slot_{field}_{slot}_{match}')


    # fixtures[d][i][j] is true if team i plays team j on field f in slot s
    fixtures = {}
    for field, matches in enumerate(team_games):
        for slot in range(len(matches)):
            for team in teams_by_field[field]:
                for opponent in teams_by_field[field]:
                    fixtures[(field, slot, team, opponent)] = model.NewBoolVar(
                        f'fixture_{field}_{slot}_{team}_{opponent}')


    team_assigned = {}  # if team is assigned to play in slot s on field f
    for field, matches in enumerate(team_games):
        for slot in range(len(matches)):
            for team in teams_by_field[field]:
                team_assigned[(field, slot, team)] = model.NewBoolVar(
                    f'team_assigned_{field}_{slot}_{team}')


    # If fixtures[(field, slot, team, opponent)] is True,
    # we know that team_assigned[(field, slot, team)] has to be True
    # and team_assigned[(field, slot, opponent)] is True
    for field, matches in enumerate(team_games):
        for slot in range(len(matches)):
            for team in teams_by_field[field]:
                for opponent in teams_by_field[field]:
                    model.AddImplication(
                        fixtures[(field, slot, team, opponent)],
                        team_assigned[(field, slot, team)]
                    )

                    model.AddImplication(
                        fixtures[(field, slot, team, opponent)],
                        team_assigned[(field, slot, opponent)]
                    )

    # If fixtures[(field, slot, team, opponent)] is True,
    # we know that game_slots[(field, slot, match)] should be True
    # where match is the index for the (team, opponent) match
    for field, matches in enumerate(team_games):
        for slot in range(len(matches)):
            for team in teams_by_field[field]:
                for opponent in teams_by_field[field]:
                    if not (team, opponent) in match_by_team_pairs:
                        continue

                    if not (field, slot, match_by_team_pairs[(team, opponent)]) in game_slots:
                        continue

                    model.AddImplication(
                        fixtures[(field, slot, team, opponent)],
                        game_slots[(
                            field, slot, match_by_team_pairs[(team, opponent)])]
                    )

    for field, matches in enumerate(team_games):
        for slot in range(len(matches)):
            for match, teams in enumerate(matches):
                model.AddImplication(
                    game_slots[(field, slot, match)],
                    team_assigned[(
                        field, slot, teams_by_field_matches[(field, match)][0])]
                )

                model.AddImplication(
                    game_slots[(field, slot, match)],
                    team_assigned[(
                        field, slot, teams_by_field_matches[(field, match)][1])]
                )

    # Constrain field time slot to hold exactly 1 match
    for field, matches in enumerate(team_games):
        for slot in range(len(matches)):
            model.AddExactlyOne(game_slots[(field, slot, match)]
                                for match, _ in enumerate(matches))

    # Constrain each match to be assigned to exactly one slot
    for field, matches in enumerate(team_games):
        for match, teams in enumerate(matches):
            model.AddExactlyOne(
                game_slots[(field, slot, match)] for slot in range(len(matches))
            )

    # Constrain a team cannot play in same time slot across fields
    for team, team_matches in matches_by_teams.items():
        for slot, fields in field_by_slot.items():
            restricted_slots = set()
            for field in fields:
                if (field, slot, team) in team_assigned:
                    restricted_slots.add((field, slot))

            if len(restricted_slots) <= 1:
                continue

            model.AddAtMostOne(team_assigned[(field, slot, team)] for field, slot in restricted_slots)

    # Constrain teams must play at least 1 game in the 1st half and 1 in the second half
    for team, team_matches in matches_by_teams.items():
        if len(team_matches) <= 1:
            continue

        first_half_matches = []
        second_half_matches = []
        for field, matches in enumerate(team_games):
            if team not in teams_by_field[field]:
                continue
            if len(matches) < 2:
                continue

            [first_half, second_half] = np.array_split(matches, 2)

            for match, teams in enumerate(first_half):
                if (field, match, team) not in team_assigned:
                    continue
                first_half_matches.append(team_assigned[(field, match, team)])

            for match, teams in enumerate(second_half):
                slot = match + len(first_half)
                if (field, slot, team) not in team_assigned:
                    continue
                second_half_matches.append(team_assigned[(field, slot, team)])

        if (len(first_half_matches) < 2) or (len(second_half_matches) < 2):
            continue

        model.AddAtLeastOne(first_half_matches)
        model.AddAtLeastOne(second_half_matches)

    # Constrain no team can play 3 consecutive slots
    for team, _ in matches_by_teams.items():
        for slot, fields in field_by_slot.items():
            restricted_slots = set()

            for field in fields:
                if (field, slot, team) not in team_assigned:
                    continue
                if (field, slot + 1, team) not in team_assigned:
                    continue
                if (field, slot + 2, team) not in team_assigned:
                    continue

                restricted_slots.add((field, slot))
                restricted_slots.add((field, slot + 1))
                restricted_slots.add((field, slot + 2))

            if len(restricted_slots) < 3:
                continue

            model.Add(sum(team_assigned[(field, slot, team)] for field, slot in restricted_slots) <= 2)

    # Try to minimize the summed indexes for each team's matches
    # This should - across teams - make sure of evenly distributing matches
    # so each team has their matches (somewhat) evenly distributed throughout the day
    index_balancing_vars = []
    for team, _ in matches_by_teams.items():
        slot_sum_balancing = []

        for field, matches in enumerate(team_games):
            for slot in range(len(matches)):
                for match, _ in enumerate(matches):
                    if (field, slot, team) not in team_assigned:
                        continue

                    v = model.NewBoolVar('v')
                    model.AddMultiplicationEquality(v, [
                        game_slots[(field, slot, match)],
                        team_assigned[(field, slot, team)]
                    ])

                    slot_sum_balancing.append(v)

        team_slot_sum_var = model.NewIntVar(0, 1_000, 'v')
        model.Add(team_slot_sum_var == sum(slot_sum_balancing))
        index_balancing_vars.append(team_slot_sum_var)

    model.Minimize(sum(index_balancing_vars))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 45.0
    status = solver.Solve(model)

    if (status != cp_model.OPTIMAL) and (status != cp_model.FEASIBLE):
        return None

    result = []
    for field, matches in enumerate(team_games):
        field_schedule = []
        for slot in range(len(matches)):
            for match, teams in enumerate(matches):
                if solver.Value(game_slots[(field, slot, match)]):
                    # Slot is the the index of the appended item.
                    field_schedule.append(team_games[field][match])
        result.append(field_schedule)

    return result

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Get best possible schedule of games')

    parser.add_argument('-d', '--data',
                    help='A multi-array of fields, games which contains tuple of teams',
                    required=True)

    args = parser.parse_args()

    result = main(team_games=json.loads(args.data))

    json_object = json.dumps(result)
    print(json_object)
