<?php
// APNs 推送通知配置
return [
    'key_id'    => env('APNS_KEY_ID', ''),
    'team_id'   => env('APNS_TEAM_ID', ''),
    'key_path'  => env('APNS_KEY_PATH', ''),
    'sandbox'   => env('APNS_SANDBOX', true),
    'bundle_id' => env('APPLE_BUNDLE_ID', 'com.yourcompany.gohome'),
];
