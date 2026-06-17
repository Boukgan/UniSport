<?php
//gmail SMTP configuration for UniSport.
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => getenv('SMTP_USER') ?: 'unisportsupport@gmail.com',
    'password' => getenv('SMTP_PASS') ?: 'qipcfqxrapfdxuvq',
    'from_email' => getenv('SMTP_FROM') ?: 'unisportsupport@gmail.com',
    'from_name' => 'UniSport – UTeM Sports Centre',

    'app_url' => getenv('APP_URL') ?: 'http://localhost/Unisport',
    'dev_mode' => false,
];