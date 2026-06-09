<?php

return [

    /*
    | Enforce that Inertia page components exist on disk — catches missing or
    | misnamed components in tests. Paths/extensions match this project's layout.
    */
    'pages' => [

        'ensure_pages_exist' => true,

        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => [
            'tsx',
            'ts',
            'jsx',
            'js',
        ],

    ],

];
