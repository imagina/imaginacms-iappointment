<?php

namespace Modules\Iappointment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Iappointment\Entities\Appointment;

class AssignAppointment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        $this->notification = app("Modules\Notification\Services\Inotification");
        $this->user = app("Modules\Iprofile\Repositories\UserApiRepository");
    }


    public function handle()
    {
        \Log::info("Checking Non-assigned appointments");
        $result = Appointment::whereNull('assigned_to')->get();

        $maxAppointments = setting('iappointment::maxAppointments');
        $roleToAssigned= setting('iappointment::roleToAssigned');

        if(count($result) > 0) {
            foreach ($result as $item){
                $userParams = [
                    'filter' => [
                        'roleId' => $roleToAssigned ?? 0,
                    ]
                ];
                $users = $this->userRepository->getItemsBy(json_decode(json_encode($userParams)));
                foreach($users as $user){
                    $appointmentCount = Appointment::where('assigned_to',$user->id)->where('status_id',2)->count();
                    if($appointmentCount < $maxAppointments){
                        $item->update(['assigned_to' => $user->id]);
                        \Log::info("Appointment #{$item->id} assigned to user {$user->present()->fullName}");
                        break;
                    }
                }
            }
        }
    }
}
