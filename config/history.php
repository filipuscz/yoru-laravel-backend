<?php

return [

    /*
    |--------------------------------------------------------------------------
    | History purge
    |--------------------------------------------------------------------------
    |
    | Tables passed to pr_purge_history. Posts are included here even though
    | they have no read API yet.
    |
    */

    'purge_tables' => [
        'tb_example_hist',
        'me_user_hist',
        'me_user_detail_hist',
        'tb_post_hist',
        'st_setting_hist',
        'st_role_hist',
        'st_permission_hist',
    ],

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
            'table' => 'tb_example_hist',
            'column' => 'example_id',
            'parameter' => 'example',
            'permission' => 'view examples',
        ],
        'users' => [
            'table' => 'me_user_hist',
            'column' => 'user_id',
            'parameter' => 'user',
            'permission' => 'view users',
        ],
        'user_details' => [
            'table' => 'me_user_detail_hist',
            'column' => 'user_detail_id',
            'parameter' => 'user',
            'permission' => 'view users',
        ],
        'settings' => [
            'table' => 'st_setting_hist',
            'column' => 'setting_id',
            'parameter' => 'setting',
            'permission' => 'view settings',
        ],
        'roles' => [
            'table' => 'st_role_hist',
            'column' => 'role_id',
            'parameter' => 'role',
            'role' => 'admin',
        ],
        'permissions' => [
            'table' => 'st_permission_hist',
            'column' => 'permission_id',
            'parameter' => 'permission',
            'role' => 'admin',
        ],
    ],

];
