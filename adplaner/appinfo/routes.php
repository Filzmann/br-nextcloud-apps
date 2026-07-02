<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'api#state', 'url' => '/api/state', 'verb' => 'GET'],
        ['name' => 'api#monthPlan', 'url' => '/api/teams/{teamCode}/months/{month}', 'verb' => 'GET'],
        ['name' => 'api#saveTeamSettings', 'url' => '/api/teams/{teamCode}/settings', 'verb' => 'POST'],
        ['name' => 'api#saveDayNote', 'url' => '/api/teams/{teamCode}/months/{month}/days/{workDate}/note', 'verb' => 'POST'],
        ['name' => 'api#addShiftCandidate', 'url' => '/api/teams/{teamCode}/months/{month}/slots/{slotId}/candidates', 'verb' => 'POST'],
        ['name' => 'api#removeShiftCandidate', 'url' => '/api/teams/{teamCode}/months/{month}/slots/{slotId}/candidates/remove', 'verb' => 'POST'],

        ['name' => 'api#yearVacation', 'url' => '/api/teams/{teamCode}/vacations/{year}', 'verb' => 'GET'],
        ['name' => 'api#createVacationRequest', 'url' => '/api/vacations', 'verb' => 'POST'],
        ['name' => 'api#deleteVacationRequest', 'url' => '/api/vacations/{requestId}/delete', 'verb' => 'POST'],
        ['name' => 'api#setVacationStatus', 'url' => '/api/teams/{teamCode}/vacations/{year}/status', 'verb' => 'POST'],
    ],
];
