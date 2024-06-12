# Eve-Kill API Documentation

This document provides detailed information on how to access and utilize the Eve-Kill API available at [https://eve-kill.com/](https://eve-kill.com/).

## API Endpoints

The table below outlines the various endpoints available, the type of HTTP request they accept, the controller and method handling the request, and the parameters required.

| URL                                                    | Request Type | Class                           | Method                     | Parameters         | Description |
|--------------------------------------------------------|--------------|---------------------------------|----------------------------|--------------------|-------------|
| /api/wars[/]                                           | GET          | EK\Controllers\Api\Wars         | wars                       | []                 |
| /api/wars/{war_id}[/]                                  | GET          | EK\Controllers\Api\Wars         | war                        | ["war_id"]         |
| /api/wars/{war_id}/killmails[/]                        | GET          | EK\Controllers\Api\Wars         | killmails                  | ["war_id"]         |
| /api/corporations[/]                                   | GET          | EK\Controllers\Api\Corporations | all                        | []                 |
| /api/corporations/count[/]                             | GET          | EK\Controllers\Api\Corporations | count                      | []                 |
| /api/corporations/{corporation_id}[/]                  | GET          | EK\Controllers\Api\Corporations | corporation                | ["corporation_id"] |
| /api/corporations[/]                                   | POST         | EK\Controllers\Api\Corporations | corporations               | []                 | If you need multiple corporations, you can post multiple IDs here. Example: [1,2,3,4]
| /api/corporations/{corporation_id}/killmails[/]        | GET          | EK\Controllers\Api\Corporations | killmails                  | ["corporation_id"] |
| /api/corporations/{corporation_id}/killmails/count[/]  | GET          | EK\Controllers\Api\Corporations | killmailsCount             | ["corporation_id"] |
| /api/corporations/{corporation_id}/killmails/latest[/] | GET          | EK\Controllers\Api\Corporations | latestKillmails            | ["corporation_id"] |
| /api/corporations/{corporation_id}/members[/]          | GET          | EK\Controllers\Api\Corporations | members                    | ["corporation_id"] |
| /api/corporations/{corporation_id}/top/characters[/]   | GET          | EK\Controllers\Api\Corporations | topCharacters              | ["corporation_id"] |
| /api/corporations/{corporation_id}/top/ships[/]        | GET          | EK\Controllers\Api\Corporations | topShips                   | ["corporation_id"] |
| /api/corporations/{corporation_id}/top/systems[/]      | GET          | EK\Controllers\Api\Corporations | topSystems                 | ["corporation_id"] |
| /api/corporations/{corporation_id}/top/regions[/]      | GET          | EK\Controllers\Api\Corporations | topRegions                 | ["corporation_id"] |
| /api/alliances[/]                                      | GET          | EK\Controllers\Api\Alliances    | all                        | []                 |
| /api/alliances/count[/]                                | GET          | EK\Controllers\Api\Alliances    | count                      | []                 |
| /api/alliances/{alliance_id}[/]                        | GET          | EK\Controllers\Api\Alliances    | alliance                   | ["alliance_id"]    |
| /api/alliances[/]                                      | POST         | EK\Controllers\Api\Alliances    | alliances                  | []                 | If you need multiple alliances, you can post multiple IDs here. Example: [1,2,3,4]
| /api/alliances/{alliance_id}/killmails[/]              | GET          | EK\Controllers\Api\Alliances    | killmails                  | ["alliance_id"]    |
| /api/alliances/{alliance_id}/killmails/count[/]        | GET          | EK\Controllers\Api\Alliances    | killmailsCount             | ["alliance_id"]    |
| /api/alliances/{alliance_id}/killmails/latest[/]       | GET          | EK\Controllers\Api\Alliances    | latestKillmails            | ["alliance_id"]    |
| /api/alliances/{alliance_id}/members[/]                | GET          | EK\Controllers\Api\Alliances    | members                    | ["alliance_id"]    |
| /api/alliances/{alliance_id}/members/characters[/]     | GET          | EK\Controllers\Api\Alliances    | characters                 | ["alliance_id"]    |
| /api/alliances/{alliance_id}/members/corporations[/]   | GET          | EK\Controllers\Api\Alliances    | corporations               | ["alliance_id"]    |
| /api/alliances/{alliance_id}/top/characters[/]         | GET          | EK\Controllers\Api\Alliances    | topCharacters              | ["alliance_id"]    |
| /api/alliances/{alliance_id}/top/corporations[/]       | GET          | EK\Controllers\Api\Alliances    | topCorporations            | ["alliance_id"]    |
| /api/alliances/{alliance_id}/top/ships[/]              | GET          | EK\Controllers\Api\Alliances    | topShips                   | ["alliance_id"]    |
| /api/alliances/{alliance_id}/top/systems[/]            | GET          | EK\Controllers\Api\Alliances    | topSystems                 | ["alliance_id"]    |
| /api/alliances/{alliance_id}/top/regions[/]            | GET          | EK\Controllers\Api\Alliances    | topRegions                 | ["alliance_id"]    |
| /api/characters[/]                                     | GET          | EK\Controllers\Api\Characters   | all                        | []                 |
| /api/characters/count[/]                               | GET          | EK\Controllers\Api\Characters   | count                      | []                 |
| /api/characters/{character_id}[/]                      | GET          | EK\Controllers\Api\Characters   | character                  | ["character_id"]   |
| /api/characters[/]                                     | POST         | EK\Controllers\Api\Characters   | characters                 | []                 | If you need multiple characters, you can post multiple IDs here. Example: [1,2,3,4]
| /api/characters/{character_id}/killmails[/]            | GET          | EK\Controllers\Api\Characters   | killmails                  | ["character_id"]   |
| /api/characters/{character_id}/killmails/count[/]      | GET          | EK\Controllers\Api\Characters   | killmailsCount             | ["character_id"]   |
| /api/characters/{character_id}/killmails/latest[/]     | GET          | EK\Controllers\Api\Characters   | latestKillmails            | ["character_id"]   |
| /api/characters/{character_id}/top/ships[/]            | GET          | EK\Controllers\Api\Characters   | topShips                   | ["character_id"]   |
| /api/characters/{character_id}/top/systems[/]          | GET          | EK\Controllers\Api\Characters   | topSystems                 | ["character_id"]   |
| /api/characters/{character_id}/top/regions[/]          | GET          | EK\Controllers\Api\Characters   | topRegions                 | ["character_id"]   |
| /api/search/{searchParam}[/]                           | GET          | EK\Controllers\Api\Search       | search                     | ["searchParam"]    |
| /api/search[/]                                         | POST         | EK\Controllers\Api\Search       | searchPost                 | []                 | You can post an array of multiple entries to this endpoint, and get results for all of them, example: ["Jita", "Amarr", "Raven", "Karbowiak"]
| /api/stats/top10characters[/{all_time:[0-1]}]          | GET          | EK\Controllers\Api\Stats        | top10Characters            | ["all_time"]       |
| /api/stats/top10corporations[/{all_time:[0-1]}]        | GET          | EK\Controllers\Api\Stats        | top10Corporations          | ["all_time"]       |
| /api/stats/top10alliances[/{all_time:[0-1]}]           | GET          | EK\Controllers\Api\Stats        | top10Alliances             | ["all_time"]       |
| /api/stats/top10solarsystems[/{all_time:[0-1]}]        | GET          | EK\Controllers\Api\Stats        | top10Systems               | ["all_time"]       |
| /api/stats/top10regions[/{all_time:[0-1]}]             | GET          | EK\Controllers\Api\Stats        | top10Regions               | ["all_time"]       |
| /api/stats/mostvaluablekillslast7days[/{limit:[0-9]+}] | GET          | EK\Controllers\Api\Stats        | mostValuableKillsLast7Days | ["limit"]          |
| /api/stats/sevendaykillcount[/]                        | GET          | EK\Controllers\Api\Stats        | sevenDayKillCount          | []                 |
| /api/killlist/latest[/{page:[0-9]+}]                   | GET          | EK\Controllers\Api\KillList     | latest                     | ["page"]           |
| /api/killmail/count[/]                                 | GET          | EK\Controllers\Api\Killmail     | count                      | []                 |
| /api/killmail/{killmail_id:[0-9]+}[/]                  | GET          | EK\Controllers\Api\Killmail     | killmail                   | ["killmail_id"]    |
| /api/killmail[/]                                       | POST         | EK\Controllers\Api\Killmail     | killmails                  | []                 | Similar to the endpoint above, you can post killmail_id's to this, and get all of the mails back