<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Server Requirements
    |--------------------------------------------------------------------------
    |
    | Production specifications for Creator Studio on modern VPS infrastructure.
    |
    */
    'core' => [
        'minPhpVersion' => '8.2.0',
    ],
    'final' => [
        'key'     => true,
        'publish' => false,
    ],
    'requirements' => [
        'php' => [
            'openssl',
            'pdo',
            'pdo_sqlite',
            'pdo_mysql',
            'pdo_pgsql',
            'mbstring',
            'tokenizer',
            'json',
            'curl',
            'fileinfo',
            'ctype',
            'xml',
            'bcmath',
        ],
        'apache' => [
            'mod_rewrite',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Folders Permissions
    |--------------------------------------------------------------------------
    |
    | Required writable folders for caching, sessions, and database persistence.
    |
    */
    'permissions' => [
        'storage/framework/' => '775',
        'storage/logs/'      => '775',
        'bootstrap/cache/'   => '775',
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Form Wizard Validation Rules & Messages
    |--------------------------------------------------------------------------
    */
    'environment' => [
        'form' => [
            'rules' => [
                'app_name'            => 'required|string|max:50',
                'environment'         => 'required|string|max:50',
                'environment_custom'  => 'required_if:environment,other|max:50',
                'app_debug'           => 'required|string',
                'app_log_level'       => 'required|string|max:50',
                'app_url'             => 'required|url',
                'database_connection' => 'required|string|max:50',
                'database_hostname'   => 'required|string|max:50',
                'database_port'       => 'required|numeric',
                'database_name'       => 'required|string|max:50',
                'database_username'   => 'required|string|max:50',
                'database_password'   => 'nullable|string|max:50',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Installed Middleware Options
    |--------------------------------------------------------------------------
    */
    'installed' => [
        'redirectOptions' => [
            'route' => [
                'name' => 'welcome',
                'data' => [],
            ],
            'abort' => [
                'type' => '404',
            ],
            'dump' => [
                'data' => 'Application is already installed.',
            ],
        ],
    ],

    'installedAlreadyAction' => '',

    /*
    |--------------------------------------------------------------------------
    | Updater Enabled
    |--------------------------------------------------------------------------
    */
    'updaterEnabled' => 'true',

];

