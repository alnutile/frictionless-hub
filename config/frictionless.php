<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default workflow stages
    |--------------------------------------------------------------------------
    |
    | Generic software-delivery stages created for every new initiative.
    | Facilitators can rename, reorder, add, or remove them afterwards.
    |
    */

    'default_stages' => [
        'Planning',
        'Coding',
        'Testing',
        'Reviewing',
        'Building',
        'Deployment',
        'Maintenance',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default DevEx dimensions
    |--------------------------------------------------------------------------
    |
    | The three dimensions from the publicly published DevEx framework
    | (Noda, Storey, Forsgren & Greiler, ACM Queue 2023). Editable per initiative.
    |
    */

    'default_dimensions' => [
        'Flow state',
        'Feedback loops',
        'Cognitive load',
    ],

];
