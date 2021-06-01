<?php


namespace Modules\Iappointment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Foundation\Bus\Dispatchable;

use Modules\Iappointment\Entities\Appointment;
use Carbon\Carbon;
use Modules\Iappointment\Entities\AppointmentStatus;
use Modules\Iappointment\Repositories\AppointmentRepository;


class MarkExpiredAppointments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {

    }

    public function handle(){
        \DB::beginTransaction();
        try{
            $now = Carbon::now();
            $appointmentDayLimit = setting('iappointment::appointmentDayLimit', null, '3'); //get inactive appointment days
            $dateLimit = $now->subDays($appointmentDayLimit);
            $inactiveAppointments = Appointment::where('status_id', 4)->whereHas('statusHistory',function($query) use($dateLimit){
                $query->whereDate('created_at','<=', $dateLimit)
                    ->where('status_id', 4);
            })->pluck('id')->toArray(); //get inactive appointments
            if($inactiveAppointments){
                \Log::info($inactiveAppointments);
                Appointment::where('status_id', 4)->whereHas('statusHistory',function($query) use($dateLimit){
                    $query->whereDate('created_at','<=', $dateLimit)
                        ->where('status_id', 4);
                })->update(['status_id',5]);
                $expiredStatus = AppointmentStatus::where('id',5)->first();
                $expiredStatus->appointments()->attach($inactiveAppointments);
            }
        }catch(\Exception $e){
            \DB::rollback();
            \Log::info('Error marking expired appointments '.$e->getMessage().' - '.$e->getFile().' Line '.$e->getLine());
        }
    }

}
