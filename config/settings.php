<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings allowlist
    |--------------------------------------------------------------------------
    |
    | PATCH /settings accepts only these keys. Secret keys stay in the database
    | for the mailer and are omitted from API responses.
    |
    */

    'allowed' => [
        'send_welcome_email' => ['secret' => false, 'default' => '1'],
        'smtp_host' => ['secret' => false, 'default' => '127.0.0.1'],
        'smtp_port' => ['secret' => false, 'default' => '2525'],
        'smtp_username' => ['secret' => false, 'default' => ''],
        'smtp_password' => ['secret' => true, 'default' => ''],
        'smtp_encryption' => ['secret' => false, 'default' => 'tls'],
        'mail_from_address' => ['secret' => false, 'default' => 'hello@example.com'],
        'mail_from_name' => ['secret' => false, 'default' => 'Laravel'],
    ],

];
