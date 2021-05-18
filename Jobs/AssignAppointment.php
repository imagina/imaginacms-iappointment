<?php

namespace Modules\Iappointment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Iappointment\Entities\Appointment;
use Carbon\Carbon;

class AssignAppointment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $notificationService;
    public $userRepository;
    public $checkinShiftRepository;

    public function __construct()
    {
        $this->notificationService = app("Modules\Notification\Services\Inotification");
        $this->userRepository = app("Modules\Iprofile\Repositories\UserApiRepository");
        $this->checkinShiftRepository = app("Modules\Icheckin\Repositories\ShiftRepository");
    }


    public function handle()
    {
        try {
            \Log::info("Checking Non-assigned appointments");
            $result = Appointment::whereNull('assigned_to')->get();

            $maxAppointments = setting('iappointment::maxAppointments');
            $roleToAssigned = setting('iappointment::roleToAssigned');

            if (count($result) > 0) {
                foreach ($result as $item) {
                    $userParams = [
                        'filter' => [
                            'roleId' => $roleToAssigned ?? 0,
                        ]
                    ];
                    $users = $this->userRepository->getItemsBy(json_decode(json_encode($userParams)));
                    foreach ($users as $user) {
                        $appointmentCount = Appointment::where('assigned_to', $user->id)->where('status_id', 2)->count();
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
                                    ]
                                );
                                \Log::info("Appointment #{$item->id} assigned to user {$user->present()->fullName}");
                            }else{
                                \Log::info("User {$user->present()->fullName} can't be assigned yet");
                            }
                            break;
                        }else{
                            \Log::info("User {$user->present()->fullName} is out of appointments");
                        }
                    }
                }
            } else {
                \Log::info('All appointments have ben assigned - Nothing to do here');
            }
        }catch(\Exception $e){
            \Log::error($e->getMessage().' '.$e->getFile().' '.$e->getLine());
        }
    }
}
