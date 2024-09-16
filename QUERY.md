# Killmail Query API Documentation

This API endpoint allows you to query killmail data with various filters, options and projections.

## Endpoint

POST /query

## Request Format

The request body should be a JSON object with the following structure:

```json
json
{
    "filter": {
    // Filter conditions
    },
    "options": {
        "limit": 10,
        "skip": 0,
        "projection": {
            // Fields to include or exclude
        }
    }
}
```

### Filter

The `filter` object specifies conditions that the killmails must meet. You can use the following MongoDB operators:

- `$gt`: Greater than
- `$gte`: Greater than or equal to
- `$lt`: Less than
- `$lte`: Less than or equal to
- `$eq`: Equal to
- `$ne`: Not equal to
- `$in`: In array
- `$nin`: Not in array
- `$exists`: Field exists

### Valid Fields for Filtering

- `killmail_id`
- `dna`
- `is_npc`
- `is_solo`
- `point_value`
- `region_id`
- `system_id`
- `system_security`
- `total_value`
- `war_id`
- `kill_time`
- `victim.ship_id`
- `victim.ship_group_id`
- `victim.character_id`
- `victim.corporation_id`
- `victim.alliance_id`
- `victim.faction_id`
- `attackers.ship_id`
- `attackers.ship_group_id`
- `attackers.character_id`
- `attackers.corporation_id`
- `attackers.alliance_id`
- `attackers.faction_id`
- `attackers.weapon_type_id`
- `item.type_id`
- `item.group_id`

### Options

- `limit`: Maximum number of results to return.
- `skip`: Number of results to skip (for pagination).
- `projection`: Specify which fields to include or exclude in the results.

## Example Queries

1. Get the 10 most recent killmails:

```json
json
{
    "filter": {},
    "options": {
        "limit": 10
    }
}
```

2. Find killmails where the victim is a specific character:

```json
json
{
    "filter": {
        "victim.character_id": 12345678
    },
    "options": {
        "limit": 20,
    }
}
```

3. Get high-value kills (over 1 billion ISK) in the last week:

```json
json
{
    "filter": {
        "total_value": {"$gt": 1000000000},
        "kill_time": {"$gte": 1234567890} // Unix timestamp for 1 week ago
    },
    "options": {
        "limit": 50
    }
}
```

4. Find solo kills in high-security space:

```json
json
{
    "filter": {
        "is_solo": true,
        "system_security": {"$gte": 0.5}
    },
    "options": {
        "limit": 30
    }
}
```

5. Get kills involving a specific ship type:

```json
json
{
    "filter": {
        "$or": [
            {"victim.ship_id": 638},
            {"attackers.ship_id": 638}
        ]
    },
    "options": {
        "limit": 25
    }
}
```

6. Find killmails with a specific item dropped:

```json
json
{
    "filter": {
        "item.type_id": 40519
    },
    "options": {
        "limit": 15,
        "projection": {
            "killmail_id": 1,
            "kill_time": 1,
            "total_value": 1,
            "victim.character_id": 1,
            "victim.corporation_id": 1
        }
    }
}
```

7. Get NPC kills in a specific region:

```json
json
{
    "filter": {
        "is_npc": true,
        "region_id": 10000002
    },
    "options": {
        "limit": 50,
        "projection": {
            "killmail_id": 1,
            "system_id": 1,
            "total_value": 1
        }
    }
}
```

8. Find kills related to a specific war:

```json
json
{
    "filter": {
        "war_id": 2
    },
    "options": {
        "limit": 40,
        "projection": {
            "killmail_id": 1,
            "war_id": 1,
            "victim.corporation_id": 1,
            "attackers.corporation_id": 1
        }
    }
}
```

Things to keep in mind:
1. The API will always exclude `_id`, `last_modified`, and `kill_time_str` fields from the results. The `kill_time` field will be returned as a Unix timestamp.
2. The API will cache results for 5 minutes.
3. All timestamps are in UTC.
4. The result is always sorted by `kill_time` in descending order.
