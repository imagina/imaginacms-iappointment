<?php

namespace Modules\Iappointment\Services;

use Modules\Iappointment\Entities\Appointment;

class AppointmentService
{
    public $appointment;
    public $category;
    public $userRepository;
    public $notificationService;
    public $checkinShiftRepository;
    public $settingsApiController;
    public $permissionsApiController;
    public $conversationService;

    public function __construct()
    {
        $this->appointment = app('Modules\Iappointment\Repositories\AppointmentRepository');
        $this->category = app('Modules\Iappointment\Repositories\CategoryRepository');
        $this->userRepository = app('Modules\Iprofile\Repositories\UserApiRepository');
        $this->notificationService = app("Modules\Notification\Services\Inotification");
        $this->checkinShiftRepository = app("Modules\Icheckin\Repositories\ShiftRepository");
        $this->settingsApiController = app("Modules\Ihelpers\Http\Controllers\Api\SettingsApiController");
        $this->permissionsApiController = app("Modules\Ihelpers\Http\Controllers\Api\PermissionsApiController");
        $this->conversationService = app("Modules\Ichat\Services\ConversationService");
    }

    /**
     * @param Category $category
     */
    function create($categoryId, $model = false){

        $customerUser = auth()->user() ?? $this->userRepository->getItem($model->entity_id,json_decode(json_encode(['filter' => []])));

        $categoryParams = [
            'include' => [],
            'filter' => [],
        ];

        $category = $this->category->getItem($categoryId, json_decode(json_encode($categoryParams)));

        $roleToAssigned = setting('iappointment::roleToAssigned');

        $userParams = [
            'filter' => [
                'roleId' => $roleToAssigned,
            ]
        ];

        $userAssignedTo = null;

        $users = $this->userRepository->getItemsBy(json_decode(json_encode($userParams)));
        foreach($users as $user){
            $userSettings = $this->settingsApiController->getAll(['userId' => $user->id]); //get user settings
            $userPermissions = $this->permissionsApiController->getAll(['userId' => $user->id]);
            if(isset($userSettings['appointmentCategories'])) {
                if (!in_array($categoryId, $userSettings['appointmentCategories'])) {
                    continue;
                }
            }else if(isset($userPermissions['iappointment.categories.index-all'])) {
                if(!$userPermissions['iappointment.categories.index-all']) {
                    continue;
                }
            }else{
                continue;
            }
            $appointmentCount =
                Appointment::where('assigned_to', $user->id)
                    ->where('customer_id','<>', $user->id)
                    ->whereIn('category_id', $userSettings['appointmentCategories'] ?? [])
                    ->where('status_id', 2)->count();
            $maxAppointments = $userSettings['maxAppointments'] ?? setting('iappointment::maxAppointments');
            if($appointmentCount < $maxAppointments){
                $shiftParams = [
                    'include' => [],
                    'user' => $user,
                    'take' => false,
                    'filter' => [
                        'repId' => $user->id,
                        'date' => [
                            'field' => 'checkout_at',
                            'to' => now()->toDateTimeString(),
                            'range' => "1"
                        ]
                    ]
                ];
                $userAssignedTo = $user;
                if(setting('iappointment::enableShifts') === '1') {
                    if (is_module_enabled('Icheckin')) {
                        $shifts = $this->checkinShiftRepository->getItemsBy(json_decode(json_encode($shiftParams)));
                        if (count($shifts) > 0) {
                            $userAssignedTo = $user;
                        } else {
                            $userAssignedTo = null;
                            \Log::info("User {$user->present()->fullName} does not have active shifts");
                        }
                    }
                }
                break;
            }else{
                \Log::info("User {$user->present()->fullName} is out of appointments");
            }
        }

        $appointmentData = [
            'description' => $category->title,
            'customer_id' => $customerUser->id,
            'status_id' => $userAssignedTo?2:1,
            'category_id' => $category->id,
            'assigned_to' => $userAssignedTo ? $userAssignedTo->id : null,
        ];
        $appointment = $this->appointment->create($appointmentData);

        if($userAssignedTo){
            if(is_module_enabled('Ichat')){
                $conversationData = [
                    'users' => [
                        $userAssignedTo->id,
                        $customerUser->id,
                    ],
                    'entity_type' => Appointment::class,
                    'entity_id' => $appointment->id,
                ];
                $this->conversationService->create($conversationData);
            }
            $this->notificationService->to([
                "email" => $userAssignedTo->email,
                "broadcast" => [$userAssignedTo->id],
                "push" => [$userAssignedTo->id],
            ])->push(
                [
                    "title" => trans("iappointment::appointments.messages.newAppointment"),
                    "message" => trans("iappointment::appointments.messages.newAppointmentContent",['name' => $userAssignedTo->present()->fullName, 'detail' => $category->title]),
                    "icon_class" => "fas fa-list-alt",
                    "buttonText" => trans("iappointment::appointments.button.take"),
                    "withButton" => true,
                    "link" => url('/ipanel/#/appointment/' . $appointment->id),
                    "setting" => [
                        "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                    ],
                    "frontEvent" => [
                        "name" => "iappointment.appoinment.was.changed",
                        "conversationId" => $appointment->conversation->id
                    ],
                ]
            );
        }

        if($customerUser){
            $this->notificationService = app("Modules\Notification\Services\Inotification");
            if($userAssignedTo){
                $this->notificationService->to([
                    "email" => $customerUser->email,
                    "broadcast" => [$customerUser->id],
                    "push" => [$customerUser->id],
                ])->push(
                    [
                        "title" => trans("iappointment::appointments.messages.newAppointment"),
                        "message" => trans("iappointment::appointments.messages.newAppointmentWithAssignedContent",['name' => $customerUser->present()->fullName, 'detail' => $category->title, 'assignedName' => $userAssignedTo->present()->fullName]),
                        "icon_class" => "fas fa-list-alt",
                        "buttonText" => trans("iappointment::appointments.button.take"),
                        "withButton" => true,
                        "link" => url('/ipanel/#/appointment/' . $appointment->id),
                        "setting" => [
                            "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                        ],
                        "actions" => [

                            [
                                "label" => "Continuar",
                                "color" => "warning"
                            ],
                            [
                                "label" => trans("iappointment::appointments.button.take"),
                                "toVueRoute" => [
                                    "name" => "api.quasar.route",
                                    "params" => [
                                        "id" => $appointment->id
                                    ]
                                ]
                            ]
                        ]
                    ]
                );
            }else{
                $this->notificationService->to([
                    "email" => $customerUser->email,
                    "broadcast" => [$customerUser->id],
                    "push" => [$customerUser->id],
                ])->push(
                    [
                        "title" => trans("iappointment::appointments.messages.newAppointment"),
                        "message" => trans("iappointment::appointments.messages.newAppointmentWithoutAssignedContent",['name' => $customerUser->present()->fullName, 'detail' => $category->title]),
                        "icon_class" => "fas fa-list-alt",
                        "buttonText" => trans("iappointment::appointments.button.take"),
                        "withButton" => true,
                        "link" => url('/ipanel/#/appointment/' . $appointment->id),
                        "setting" => [
                            "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                        ],
                        "mode" => "modal",
                        "actions" => [

                            [
                                "label" => "Continuar",
                                "color" => "warning"
                            ],
                            [
                                "label" => "Ver Planes",
                                "toUrl" => url("/planes")
                            ]
                        ]
                    ]
                );
            }
        }

        return $appointment;
    }
}
