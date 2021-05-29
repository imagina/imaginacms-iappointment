<?php

namespace Modules\Iappointment\Services;

use Modules\Iappointment\Entities\Appointment;
use Modules\Ichat\Entities\Conversation;

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

    function assign($categoryId = null, $model = false){

        $customerUser = auth()->user() ?? ($model ? $this->userRepository->getItem($model->entity_id,json_decode(json_encode(['filter' => []]))) : null);

        $categoryParams = [
            'include' => [],
            'filter' => [],
        ];

        $category = $this->category->getItem($categoryId, json_decode(json_encode($categoryParams)));

        if($customerUser){
            \Log::info("Creating new Appointment");
            $appointmentExist = Appointment::where('customer_id', $customerUser->id)
                ->where('category_id', $categoryId)
                ->whereIn('status_id',[4,6])->count();
            if($appointmentExist == 0) {
                $appointmentData = [
                    'description' => $category->title,
                    'customer_id' => $customerUser->id,
                    'status_id' => 1,
                    'category_id' => $category->id,
                ];
                $appointment = $this->appointment->create($appointmentData);

                $this->notificationService = app("Modules\Notification\Services\Inotification");
                $this->notificationService->to([
                    "email" => $customerUser->email,
                    "broadcast" => [$customerUser->id],
                    "push" => [$customerUser->id],
                ])->push(
                    [
                        "title" => trans("iappointment::appointments.messages.newAppointment"),
                        "message" => trans("iappointment::appointments.messages.newAppointmentContent",['name' => $customerUser->present()->fullName, 'detail' => $appointment->description]),
                        "icon_class" => "fas fa-list-alt",
                        "buttonText" => trans("iappointment::appointments.button.take"),
                        "withButton" => true,
                        "link" => url('/ipanel/#/appoimtment/' . $appointment->id),
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
                                "label" => trans("iappointment::appointments.button.take"),
                                "toVueRoute" => [
                                    "name" => "qappointment.panel.appointments.index",
                                    "params" => [
                                        "id" => $appointment->id
                                    ]
                                ],
                            ]
                        ]
                    ]
                );

                return $appointment;
            }
        }

        $roleToAssigned = setting('iappointment::roleToAssigned');

        $userParams = [
            'filter' => [
                'roleId' => $roleToAssigned,
            ]
        ];

        $users = $this->userRepository->getItemsBy(json_decode(json_encode($userParams)));

        $userAssignedTo = null;

        foreach ($users as $professionalUser) {
            $canBeAssigned = false;

            $userSettings = $this->settingsApiController->getAll(['userId' => $professionalUser->id]); //get user settings
            $userPermissions = $this->permissionsApiController->getAll(['userId' => $professionalUser->id]);
            $appointmentCount =
                Appointment::where('assigned_to', $professionalUser->id)
                    ->where(function($query) use($professionalUser){
                        $query->where('customer_id','<>', $professionalUser->id)
                            ->orWhereNull('customer_id');
                    })
                    ->whereIn('category_id', $userSettings['appointmentCategories'] ?? [])
                    ->where('status_id', '>=', 2)->count();
            $maxAppointments = $userSettings['maxAppointments'] ?? setting('iappointment::maxAppointments');

            \Log::info("Appointment count for {$professionalUser->present()->fullName} > $appointmentCount - $maxAppointments");

            $result = Appointment::where('status_id', 1)->where('customer_id','<>',$professionalUser->id)->get();
            if (count($result) > 0) {
                foreach ($result as $item) {
                    if(isset($userSettings['appointmentCategories'])) {
                        if (!in_array($item->category_id, $userSettings['appointmentCategories'])) {
                            $canBeAssigned = false;
                            continue;
                        }
                    }else if(isset($userPermissions['iappointment.categories.index-all'])) {
                        if(!$userPermissions['iappointment.categories.index-all']) {
                            $canBeAssigned = false;
                            continue;
                        }
                    }else{
                        $canBeAssigned = false;
                        continue;
                    }
                    if ($appointmentCount < $maxAppointments) {
                        $shiftParams = [
                            'include' => [],
                            'user' => $professionalUser,
                            'take' => false,
                            'filter' => [
                                'repId' => $professionalUser->id,
                                'active' => '1',
                            ]
                        ];
                        $canBeAssigned = true;
                        if(setting('iappointment::enableShifts') === '1') {
                            if (is_module_enabled('Icheckin')) {
                                $shifts = $this->checkinShiftRepository->getItemsBy(json_decode(json_encode($shiftParams)));
                                if (count($shifts) > 0) {
                                    $canBeAssigned = true;
                                } else {
                                    $canBeAssigned = false;
                                    \Log::info("User {$professionalUser->present()->fullName} does not have active shifts");
                                }
                            }
                        }
                        if($canBeAssigned){
                            $customerUser = $item->customer;
                            $prevAssignedTo = $item->assignedTo;
                            $appointmentConversation = Conversation::where('entity_type', Appointment::class)
                                ->where('entity_id', $item->id)->first();

                            $this->appointment->updateBy($item->id ,[
                                'assigned_to' => $professionalUser->id,
                                'status_id' => 2,
                            ]);

                            if(!$appointmentConversation) {
                                $conversationData = [
                                    'users' => [
                                        $professionalUser->id,
                                        $customerUser->id,
                                    ],
                                    'entity_type' => Appointment::class,
                                    'entity_id' => $item->id,
                                ];
                                $this->conversationService->create($conversationData);
                                $appointmentConversation = Conversation::where('entity_type', Appointment::class)
                                    ->where('entity_id', $item->id)->first();
                            }


                            if($prevAssignedTo && $prevAssignedTo->id != $professionalUser->id){
                                $appointmentConversation->users()->sync([
                                    $customerUser->id,
                                    $professionalUser->id,
                                ]);
                            }

                            $this->notificationService->to([
                                "email" => $professionalUser->email,
                                "broadcast" => [$professionalUser->id],
                                "push" => [$professionalUser->id],
                            ])->push(
                                [
                                    "title" => trans("iappointment::appointments.messages.newAppointment"),
                                    "message" => trans("iappointment::appointments.messages.newAppointmentContent", ['name' => $professionalUser->present()->fullName, 'detail' => $item->category->title]),
                                    "icon_class" => "fas fa-list-alt",
                                    "buttonText" => trans("iappointment::appointments.button.take"),
                                    "withButton" => true,
                                    "link" => url('/ipanel/#/appoimtment/' . $item->id),
                                    "setting" => [
                                        "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                                    ],
                                    "frontEvent" => [
                                        "name" => "iappointment.appoinment.was.changed",
                                        "conversationId" => $appointmentConversation->id
                                    ],
                                ]
                            );
                        }
                        \Log::info("Appointment #{$item->id} assigned to user {$professionalUser->present()->fullName}");
                        if($customerUser){
                            if(is_module_enabled('Ichat')){
                                $existsConversation = Conversation::where('entity_type',Appointment::class)
                                    ->where('entity_id', $item->id)->count();
                                $conversationData = [
                                    'users' => [
                                        $professionalUser->id,
                                        $customerUser->id,
                                    ],
                                    'entity_type' => Appointment::class,
                                    'entity_id' => $item->id,
                                ];
                                if($existsConversation == 0)
                                    $this->conversationService->create($conversationData);
                            }
                            $this->notificationService = app("Modules\Notification\Services\Inotification");
                            $this->notificationService->to([
                                "email" => $customerUser->email,
                                "broadcast" => [$customerUser->id],
                                "push" => [$customerUser->id],
                            ])->push(
                                [
                                    "title" => trans("iappointment::appointments.messages.newAppointment"),
                                    "message" => trans("iappointment::appointments.messages.newAppointmentWithAssignedContent",['name' => $customerUser->present()->fullName, 'detail' => $item->category->title]),
                                    "icon_class" => "fas fa-list-alt",
                                    "buttonText" => trans("iappointment::appointments.button.take"),
                                    "withButton" => true,
                                    "link" => url('/ipanel/#/appoimtment/' . $item->id),
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
                                            "label" => trans("iappointment::appointments.button.take"),
                                            "toVueRoute" => [
                                                "name" => "qappointment.panel.appointments.index",
                                                "params" => [
                                                    "id" => $item->id
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            );
                        }else{
                            \Log::info("User {$professionalUser->present()->fullName} can't be assigned yet");
                        }
                        break;
                    }else{
                        \Log::info("User {$professionalUser->present()->fullName} is out of appointments");
                        $canBeAssigned = false;
                    }
                }
                if(!$canBeAssigned){
                    $adminEmails = setting('isite::emails');
                    $this->notificationService->to([
                        "email" => $adminEmails,
                    ])->push(
                        [
                            "title" => trans("iappointment::appointments.messages.appointmentNotAssigned"),
                            "message" => trans("iappointment::appointments.messages.appointmentNotAssignedContent", ['detail' => $item->category->title]),
                            "icon_class" => "fas fa-list-alt",
                            "buttonText" => trans("iappointment::appointments.button.take"),
                            "withButton" => true,
                            "link" => url('/ipanel/#/appoimtment/' . $item->id),
                            "setting" => [
                                "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                            ],
                        ]
                    );
                }
            }
        }
    }
}
