<?php

return [
    'tile_url' => env(
        'MAP_TILE_URL_TEMPLATE',
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    ),
    'attribution' => env(
        'MAP_TILE_ATTRIBUTION',
        '© OpenStreetMap contributors',
    ),
];
