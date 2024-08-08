<?php

namespace EK\Websockets;

use EK\Http\Websocket;

class Kills extends Websocket
{
    public string $endpoint = '/kills';

    public function handle(array $data): void
    {
        $killTime = strtotime($data['kill_time']);
        $sendToTypes = ['alliance_id', 'corporation_id', 'faction_id', 'character_id', 'ship_id', 'group_id', 'weapon_type_id'];

        $emit = [
            'system_id' => [$data['system_id']],
            'region_id' => [$data['region_id']],
        ];

        foreach(array_merge($data['attackers'], [$data['victim']]) as $participant) {
            foreach($sendToTypes as $type) {
                if(!empty($participant[$type]) && !in_array($participant[$type], [0, null])) {
                    $emit[$type][] = $participant[$type];
                }
            }
        }

        $emitToClients = [];
        foreach($this->clients as $client) {
            $clientSubscriptions = json_decode($client['data'], true) ?? [];
            $intersect = array_intersect_key($emit, $clientSubscriptions);
            foreach($intersect as $type => $ids) {
                if (in_array($clientSubscriptions[$type], $ids)) {
                    if ($killTime < $clientSubscriptions['connection_time']) {
                        continue;
                    }

                    $emitToClients[$client['fd']] = $client['fd'];
                    break;
                }
            }

            // If the client is subscribing to the `all` type, then we need to emit to them
            if (in_array('all', $clientSubscriptions)) {
                $emitToClients[$client['fd']] = $client['fd'];
            }
        }


        foreach($emitToClients as $fd) {
            $this->send($fd, json_encode($data));
        }
    }
}
