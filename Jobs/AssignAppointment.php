<?php

namespace Modules\Iappointment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Iappointment\Entities\Appointment;
use Modules\Ichat\Entities\Conversation;
use Carbon\Carbon;

class AssignAppointment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $notificationService;
    public $userRepository;
    public $checkinShiftRepository;
    public $settingsApiController;
    public $permissionsApiController;
    public $conversationService;
    public $statusService;

    public function __construct()
    {
        $this->notificationService = app("Modules\Notification\Services\Inotification");
        $this->userRepository = app("Modules\Iprofile\Repositories\UserApiRepository");
        $this->checkinShiftRepository = app("Modules\Icheckin\Repositories\ShiftRepository");
        $this->settingsApiController = app("Modules\Ihelpers\Http\Controllers\Api\SettingsApiController");
        $this->permissionsApiController = app("Modules\Ihelpers\Http\Controllers\Api\PermissionsApiController");
        $this->conversationService = app("Modules\Ichat\Services\ConversationService");
        $this->statusService = app("Modules\Iappointment\Services\AppointmentStatusService");
    }


    public function handle()
    {
        try {
            \Log::info("Checking Non-assigned appointments");

            $roleToAssigned = setting('iappointment::roleToAssigned');

            $userParams = [
                'filter' => [
                    'roleId' => $roleToAssigned ?? 0,
                ]
            ];
            $users = $this->userRepository->getItemsBy(json_decode(json_encode($userParams)));

            foreach ($users as $professionalUser) {
                $canBeAssigned = false;
                $result = Appointment::where('status_id', 1)->where('customer_id','<>',$professionalUser->id)->get();
                if (count($result) > 0) {
                    foreach ($result as $item) {
                        $userSettings = $this->settingsApiController->getAll(['userId' => $professionalUser->id]); //get user settings
                        $userPermissions = $this->permissionsApiController->getAll(['userId' => $professionalUser->id]);
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
                                $appointmentConversation = Conversation::where('entity_type', Appointment::class)
                                    ->where('entity_id', $item->id)->first();
                                /*$item->update([
                                    'assigned_to' => $user->id,
                                    'status_id' => 2,
                                ]);*/

                                $prevAssignedTo = $item;

                                $this->statusService->setStatus($item->id, 2, $professionalUser->id);

                                if($appointmentConversation) {
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
                                                "name" => "new.appointment.assigned",
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
                                                        "name" => "api.quasar.route",
                                                        "params" => [
                                                            "id" => $item->id
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    );
                                }
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
        }catch(\Exception $e){
            \Log::error($e->getMessage().' '.$e->getFile().' '.$e->getLine());
        }
    }
}
