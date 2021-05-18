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

    public function __construct()
    {
        $this->appointment = app('Modules\Iappointment\Repositories\AppointmentRepository');
        $this->category = app('Modules\Iappointment\Repositories\CategoryRepository');
        $this->userRepository = app('Modules\Iprofile\Repositories\UserApiRepository');
        $this->notificationService = app("Modules\Notification\Services\Inotification");
        $this->checkinShiftRepository = app("Modules\Icheckin\Repositories\ShiftRepository");
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

        $maxAppointments = setting('iappointment::maxAppointments');
        $roleToAssigned= setting('iappointment::roleToAssigned');

        $userParams = [
            'filter' => [
                'roleId' => $roleToAssigned ?? 0,
            ]
        ];

        $userAssignedTo = null;

        $users = $this->userRepository->getItemsBy(json_decode(json_encode($userParams)));
        foreach($users as $user){
            $appointmentCount = Appointment::where('assigned_to',$user->id)->where('status_id',2)->count();
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
            'status_id' => 1,
            'category_id' => $category->id,
            'assigned_to' => $userAssignedTo->id,
        ];
        $appointment = $this->appointment->create($appointmentData);

        if($userAssignedTo){
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
                    "link" => url('/ipanel/#/appoimtment/' . $appointment->id),
                    "setting" => [
                        "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                    ],
                ]
            );
        }

        if($customerUser){
            $this->notificationService = app("Modules\Notification\Services\Inotification");
            $this->notificationService->to([
                "email" => $customerUser->email,
                "broadcast" => [$customerUser->id],
                "push" => [$customerUser->id],
            ])->push(
                [
                    "title" => trans("iappointment::appointments.messages.newAppointment"),
                    "message" => trans("iappointment::appointments.messages.newAppointmentContent",['name' => $customerUser->present()->fullName, 'detail' => $category->title]),
                    "icon_class" => "fas fa-list-alt",
                    "buttonText" => trans("iappointment::appointments.button.take"),
                    "withButton" => true,
                    "link" => url('/ipanel/#/appoimtment/' . $appointment->id),
                    "setting" => [
                        "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                    ],
                ]
            );
        }

        return $appointment;
    }
}
