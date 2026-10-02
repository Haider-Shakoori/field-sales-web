<?php

return [
    'tile_url' => env(
        'MAP_TILE_URL_TEMPLATE',
        'https://maps.fieldpulse.businessos.af/styles/afghanistan/{z}/{x}/{y}.png',
    ),
    'attribution' => env(
        'MAP_TILE_ATTRIBUTION',
        '© OpenStreetMap contributors · Geofabrik · BusinessOS',
    ),
];
