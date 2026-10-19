<?php

$tables = [
    'examples' => [
        'source' => 'tb_examples',
        'history' => 'tb_example_hist',
        'record' => 'example_id',
        'function' => 'fn_tb_example_hist',
        'trigger' => 'trg_tb_examples_hist',
        'soft_deletes' => true,
        'hidden' => [],
    ],
    'users' => [
        'source' => 'me_users',
        'history' => 'me_user_hist',
        'record' => 'user_id',
        'function' => 'fn_me_user_hist',
        'trigger' => 'trg_me_users_hist',
        'soft_deletes' => true,
        'hidden' => ['password', 'remember_token'],
    ],
    'user_details' => [
        'source' => 'me_user_details',
        'history' => 'me_user_detail_hist',
        'record' => 'user_detail_id',
        'function' => 'fn_me_user_detail_hist',
        'trigger' => 'trg_me_user_details_hist',
        'soft_deletes' => true,
        'hidden' => ['transaction_pin'],
    ],
    'settings' => [
        'source' => 'st_settings',
        'history' => 'st_setting_hist',
        'record' => 'setting_id',
        'function' => 'fn_st_setting_hist',
        'trigger' => 'trg_st_settings_hist',
        'soft_deletes' => false,
        'hidden' => [],
        'redact_when' => [
            'column' => 'key',
            'value' => 'smtp_password',
            'hidden' => ['value'],
        ],
    ],
    'roles' => [
        'source' => 'st_roles',
        'history' => 'st_role_hist',
        'record' => 'role_id',
        'function' => 'fn_st_role_hist',
        'trigger' => 'trg_st_roles_hist',
        'soft_deletes' => false,
        'hidden' => [],
    ],
    'permissions' => [
        'source' => 'st_permissions',
        'history' => 'st_permission_hist',
        'record' => 'permission_id',
        'function' => 'fn_st_permission_hist',
        'trigger' => 'trg_st_permissions_hist',
        'soft_deletes' => false,
        'hidden' => [],
    ],
];

return [

    'tables' => $tables,

    /*
    |--------------------------------------------------------------------------
    | History purge
    |--------------------------------------------------------------------------
    |
    | Tables passed to pr_purge_history.
    |
    */

    'purge_tables' => array_column($tables, 'history'),

    /*
    |--------------------------------------------------------------------------
    | History read API
    |--------------------------------------------------------------------------
    |
    | Each source is one audited table. permission is a Spatie ability on the
    | api guard. role is used when the route is limited to that role instead.
    |
    */

    'sources' => [
        'examples' => [
            'table' => $tables['examples']['history'],
            'column' => $tables['examples']['record'],
            'parameter' => 'example',
            'permission' => 'view examples',
        ],
        'users' => [
            'table' => $tables['users']['history'],
            'column' => $tables['users']['record'],
            'parameter' => 'user',
            'permission' => 'view users',
        ],
        'user_details' => [
            'table' => $tables['user_details']['history'],
            'column' => $tables['user_details']['record'],
            'parameter' => 'user',
            'permission' => 'view users',
        ],
        'settings' => [
            'table' => $tables['settings']['history'],
            'column' => $tables['settings']['record'],
            'parameter' => 'setting',
            'permission' => 'view settings',
        ],
        'roles' => [
            'table' => $tables['roles']['history'],
            'column' => $tables['roles']['record'],
            'parameter' => 'role',
            'role' => 'admin',
        ],
        'permissions' => [
            'table' => $tables['permissions']['history'],
            'column' => $tables['permissions']['record'],
            'parameter' => 'permission',
            'role' => 'admin',
        ],
    ],

];
