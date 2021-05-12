<?php

return [
    'roleToCustomer' => [
        'name' => 'iappointment::roleToCustomer',
        'value' => null,
        'type' => 'select',
        'props' => [
            'label' => 'iappointment::settings.roleAsCustomer',
            'useChips' => true
        ],
        'loadOptions' => [
            'apiRoute' => 'apiRoutes.quser.roles',
            'select' => ['label' => 'name', 'id' => 'id']
        ]
    ],
    'roleToAssigned' => [
        'name' => 'iappointment::roleToAssigned',
        'value' => null,
        'type' => 'select',
        'props' => [
            'label' => 'iappointment::settings.roleAsAssigned',
            'useChips' => true
        ],
        'loadOptions' => [
            'apiRoute' => 'apiRoutes.quser.roles',
            'select' => ['label' => 'name', 'id' => 'id']
        ]
    ],
    'enableChat' => [
        'name' => 'iappointment::enableChat',
        'value' => '0',
        'type' => 'checkbox',
        'props' => [
            'label' => 'iappointment::settings.enableChat',
            'trueValue' => '1',
            'falseValue' => '0'
        ],
    ],
    'maxAppointments' => [
        'name' => 'iappointment::maxAppointments',
        'value' => '1',
        'type' => 'input',
        'props' => [
            'label' => 'iappointment::settings.maxAppointments',
            'type' => 'number',
            'min' => '1',
        ],
    ],
];
