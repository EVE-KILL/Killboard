<?php

namespace EK\Helpers;

class Universe
{
    public function fixRegionNames(string $region): string
    {
        $properNamedRegions = [
            'BlackRise' => 'Black Rise',
            'CloudRing' => 'Cloud Ring',
            'CobaltEdge' => 'Cobalt Edge',
            'EtheriumReach' => 'Etherium Reach',
            'GreatWildlands' => 'Great Wildlands',
            'MoldenHeath' => 'Molden Heath',
            'OuterPassage' => 'Outer Passage',
            'OuterRing' => 'Outer Ring',
            'ParagonSoul' => 'Paragon Soul',
            'PeriodBasis' => 'Period Basis',
            'PerrigenFalls' => 'Perrigen Falls',
            'PureBlind' => 'Pure Blind',
            'ScaldingPass' => 'Scalding Pass',
            'SinqLaison' => 'Sinq Laison',
            'TheBleakLands' => 'The Bleak Lands',
            'TheCitadel' => 'The Citadel',
            'TheForge' => 'The Forge',
            'TheKalevalaExpanse' => 'The Kalevala Expanse',
            'TheSpire' => 'The Spire',
            'ValeoftheSilent' => 'Vale of the Silent',
            'VergeVendor' => 'Verge Vendor',
            'WickedCreek' => 'Wicked Creek',
        ];

        return $properNamedRegions[$region] ?? $region;
    }
}
