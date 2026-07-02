<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'api#state', 'url' => '/api/state', 'verb' => 'GET'],
        ['name' => 'api#updateSettings', 'url' => '/api/settings', 'verb' => 'POST'],
        ['name' => 'api#createMeeting', 'url' => '/api/meetings', 'verb' => 'POST'],
        ['name' => 'api#planNextRegularMeeting', 'url' => '/api/meetings/next-regular', 'verb' => 'POST'],
        ['name' => 'api#deleteMeeting', 'url' => '/api/meetings/{meetingId}/delete', 'verb' => 'POST'],
        ['name' => 'api#addTop', 'url' => '/api/meetings/{meetingId}/tops', 'verb' => 'POST'],
        ['name' => 'api#moveTop', 'url' => '/api/meetings/{meetingId}/tops/{topId}/move', 'verb' => 'POST'],
        ['name' => 'api#changeTopDepth', 'url' => '/api/meetings/{meetingId}/tops/{topId}/depth', 'verb' => 'POST'],
        ['name' => 'api#updateTopSubject', 'url' => '/api/meetings/{meetingId}/tops/{topId}/subject', 'verb' => 'POST'],
        ['name' => 'api#deleteTop', 'url' => '/api/meetings/{meetingId}/tops/{topId}/delete', 'verb' => 'POST'],
        ['name' => 'api#addProtocolBlock', 'url' => '/api/meetings/{meetingId}/tops/{topId}/protocol-blocks', 'verb' => 'POST'],
        ['name' => 'api#updateProtocolBlock', 'url' => '/api/meetings/{meetingId}/tops/{topId}/protocol-blocks/{blockId}', 'verb' => 'POST'],

        ['name' => 'api#generateInvitation', 'url' => '/api/meetings/{meetingId}/invitation', 'verb' => 'POST'],
        ['name' => 'api#generateProtocol', 'url' => '/api/meetings/{meetingId}/protocol', 'verb' => 'POST'],
        ['name' => 'api#generateResolutions', 'url' => '/api/meetings/{meetingId}/resolutions', 'verb' => 'POST'],

        ['name' => 'api#seedDemo', 'url' => '/api/demo', 'verb' => 'POST'],
    ],
];
