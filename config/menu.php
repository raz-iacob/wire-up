<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Menu item icons
    |--------------------------------------------------------------------------
    |
    | The icons an admin may choose for a menu item. Constrained to a curated
    | set of bundled Heroicons so that rendering never references a missing
    | icon view (which would throw at runtime).
    |
    */

    'icons' => [
        'home', 'document-text', 'book-open', 'academic-cap', 'cog-6-tooth',
        'wrench-screwdriver', 'puzzle-piece', 'currency-dollar', 'user', 'user-group',
        'envelope', 'phone', 'map-pin', 'globe-alt', 'star', 'heart', 'bolt', 'sparkles',
        'light-bulb', 'rocket-launch', 'chart-bar', 'folder', 'tag', 'calendar-days',
        'clock', 'bell', 'shield-check', 'lock-closed', 'question-mark-circle',
        'information-circle', 'arrow-right', 'link', 'photo', 'play', 'squares-2x2',
        'list-bullet', 'newspaper', 'briefcase', 'building-office-2', 'shopping-cart',
        'gift', 'flag', 'hashtag', 'beaker', 'code-bracket', 'command-line', 'cpu-chip', 'cloud',
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu item badge colours
    |--------------------------------------------------------------------------
    |
    | The badge colours an admin may choose for a menu item. Each key maps to a
    | concrete set of utility classes in the sidebar navlist component.
    |
    */

    'badge_colors' => ['zinc', 'primary', 'green', 'red', 'amber', 'blue', 'purple'],

];
