<?php

namespace Modules\Iappointment\Services;

use Modules\Iappointment\Entities\Appointment;

class AppointmentService
{
    public $appointment;
    public $category;
    public $userRepository;
    public $notificationService;

    public function __construct()
    {
        $this->appointment = app('Modules\Iappointment\Repositories\AppointmentRepository');
        $this->category = app('Modules\Iappointment\Repositories\CategoryRepository');
        $this->userRepository = app('Modules\Iprofile\Repositories\UserApiRepository');
        $this->notificationService = app("Modules\Notification\Services\Inotification");
    }

    /**
     * @param Category $category
     */
    function create($categoryId, $model = false){

        $user = auth()->user();

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
                $userAssignedTo = $user->id;
                //send notification by email, broadcast and push -- by default only send by email
                $this->notificationService->to([
                    "email" => $user->email,
                    "broadcast" => [$user->id],
                    "push" => [$user->id],
                ])->push(
                    [
                        "title" => trans("iappointment::appointments.messages.newAppointment"),
                        "message" => trans("iappointment::appointments.messages.newAppointmentContent",['name' => $user->present()->fullName]),
                        "icon_class" => "fas fa-shopping-cart",
                        "link" => "",
                        "content" => "icommerce::emails.order",
                        "view" => "icommerce::emails.Order",
                        "setting" => [
                            "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                        ],
                    ]
                );
                break;
            }
        }

        $appointmentData = [
            'description' => '--',
            'customer_id' => $model ? $model->entity_id : $user->id,
            'status_id' => 1,
            'category_id' => $category->id,
            'assigned_to' => $userAssignedTo,
        ];
        $appointment = $this->appointment->create($appointmentData);
        return $appointment;
    }
}
