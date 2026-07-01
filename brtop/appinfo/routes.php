<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'api#state', 'url' => '/api/state', 'verb' => 'GET'],
        ['name' => 'api#updateSettings', 'url' => '/api/settings', 'verb' => 'POST'],
        ['name' => 'api#createMeeting', 'url' => '/api/meetings', 'verb' => 'POST'],
        ['name' => 'api#planNextRegularMeeting', 'url' => '/api/meetings/next-regular', 'verb' => 'POST'],
        ['name' => 'api#addTop', 'url' => '/api/meetings/{meetingId}/tops', 'verb' => 'POST'],

        ['name' => 'api#generateInvitation', 'url' => '/api/meetings/{meetingId}/invitation', 'verb' => 'POST'],
        ['name' => 'api#generateProtocol', 'url' => '/api/meetings/{meetingId}/protocol', 'verb' => 'POST'],
        ['name' => 'api#generateResolutions', 'url' => '/api/meetings/{meetingId}/resolutions', 'verb' => 'POST'],

        ['name' => 'api#seedDemo', 'url' => '/api/demo', 'verb' => 'POST'],
    ],
];
