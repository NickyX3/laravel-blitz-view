<?php

return [
    'templates_folder'      => 'blitz_view',
    'cache_type'            => 'file',
    'cache_enabled'         => false,
    'compiled_folder'       => 'blitz_compiled',
    'scope_lookup_limit'    => 8,
    'php_callbacks_first'   => 1,
    'namespace_finder'      => [
        'App\Helpers',
        'Illuminate\Support',
        'Illuminate\Support\Facades'
    ]
];
