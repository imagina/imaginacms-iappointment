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

    public function __construct()
    {
        $this->notificationService = app("Modules\Notification\Services\Inotification");
        $this->userRepository = app("Modules\Iprofile\Repositories\UserApiRepository");
        $this->checkinShiftRepository = app("Modules\Icheckin\Repositories\ShiftRepository");
        $this->settingsApiController = app("Modules\Ihelpers\Http\Controllers\Api\SettingsApiController");
        $this->permissionsApiController = app("Modules\Ihelpers\Http\Controllers\Api\PermissionsApiController");
        $this->conversationService = app("Modules\Ichat\Services\ConversationService");
    }


    public function handle()
    {
        try {
            \Log::info("Checking Non-assigned appointments");

            $roleToAssigned = setting('iappointment::roleToAssigned');

            $userParams = [
                'filter' => [
                    'roleId' => 0,
                ]
            ];
            $users = $this->userRepository->getItemsBy(json_decode(json_encode($userParams)));

            foreach ($users as $user) {
                $isAssigned = false;
                $result = Appointment::whereNull('assigned_to')->where('customer_id','<>',$user->id)->get();
                if (count($result) > 0) {
                    foreach ($result as $item) {
                        $userSettings = $this->settingsApiController->getAll(['userId' => $user->id]); //get user settings
                        $userPermissions = $this->permissionsApiController->getAll(['userId' => $user->id]);
                        if(isset($userSettings['appointmentCategories'])) {
                            if (!in_array($item->category_id, $userSettings['appointmentCategories'])) {
                                $isAssigned = false;
                                continue;
                            }
                        }else if(isset($userPermissions['iappointment.categories.index-all'])) {
                            \Log::info($userPermissions);
                            if(!$userPermissions['iappointment.categories.index-all']) {
                                $isAssigned = false;
                                continue;
                            }
                        }else{
                            $isAssigned = false;
                            continue;
                        }
                        $appointmentCount =
                            Appointment::where('assigned_to', $user->id)
                                ->where(function($query) use($user){
                                    $query->where('customer_id','<>', $user->id)
                                    ->orWhereNull('customer_id');
                                })
                                ->whereIn('category_id', $userSettings['appointmentCategories'] ?? [])
                                ->where('status_id', '>=', 2)->count();
                        $maxAppointments = $userSettings['maxAppointments'] ?? setting('iappointment::maxAppointments');
                        \Log::info("Appointment count for {$user->present()->fullName} > $appointmentCount - $maxAppointments");
                        if ($appointmentCount < $maxAppointments) {
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
                            $isAssigned = true;
                            if(setting('iappointment::enableShifts') === '1') {
                                if (is_module_enabled('Icheckin')) {
                                    $shifts = $this->checkinShiftRepository->getItemsBy(json_decode(json_encode($shiftParams)));
                                    if (count($shifts) > 0) {
                                        $isAssigned = true;
                                    } else {
                                        $isAssigned = false;
                                        \Log::info("User {$user->present()->fullName} does not have active shifts");
                                    }
                                }
                            }
                            if($isAssigned){
                                $customerUser = $item->customer;
                                $appointmentConversation = Conversation::where('entity_type', Appointment::class)
                                    ->where('entity_id', $item->id)->first();
                                $item->update([
                                    'assigned_to' => $user->id,
                                    'status_id' => 2,
                                ]);
                                $this->notificationService->to([
                                    "email" => $user->email,
                                    "broadcast" => [$user->id],
                                    "push" => [$user->id],
                                ])->push(
                                    [
                                        "title" => trans("iappointment::appointments.messages.newAppointment"),
                                        "message" => trans("iappointment::appointments.messages.newAppointmentContent", ['name' => $user->present()->fullName, 'detail' => $item->category->title]),
                                        "icon_class" => "fas fa-list-alt",
                                        "buttonText" => trans("iappointment::appointments.button.take"),
                                        "withButton" => true,
                                        "link" => url('/ipanel/#/appoimtment/' . $item->id),
                                        "setting" => [
                                            "saveInDatabase" => 1 // now, the notifications with type broadcast need to be save in database to really send the notification
                                        ],
                                        "fromEvent" => [
                                            "name" => "new.appointment.assigned",
                                            "conversationId" => $appointmentConversation->id
                                        ],
                                    ]
                                );
                                \Log::info("Appointment #{$item->id} assigned to user {$user->present()->fullName}");
                                if($customerUser){
                                    if(is_module_enabled('Ichat')){
                                        $existsConversation = Modules\Ichat\Entities\Conversation::where('entity_type',Appointment::class)
                                            ->where('entity_id', $item->id)->count();
                                        $conversationData = [
                                            'users' => [
                                                $user->id,
                                                $customerUser->id,
                                            ],
                                            'entity_type' => Appointment::class,
                                            'entity_id' => $item->id,
                                        ];
                                        if($existsConversation == 0)
                                            $this->conversationService->create($conversationData);
                                    }
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
                                \Log::info("User {$user->present()->fullName} can't be assigned yet");
                            }
                            break;
                        }else{
                            \Log::info("User {$user->present()->fullName} is out of appointments");
                            $isAssigned = false;
                        }
                    }
                    if(!$isAssigned){
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
                } else {
                    \Log::info('All appointments have ben assigned - Nothing to do here');
                }
            }
        }catch(\Exception $e){
            \Log::error($e->getMessage().' '.$e->getFile().' '.$e->getLine());
        }
    }
}
