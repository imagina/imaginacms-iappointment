<?php

namespace Modules\Iappointment\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Iappointment\Entities\AppointmentStatus;
use Modules\Iappointment\Entities\AppointmentStatusTranslation;

class AppointmentStatusTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $statuses = config('asgard.iappointment.config.appointmentStatuses');

        foreach ($statuses as $status) {

            $statusTrans = $status['title'];

            foreach (['en', 'es'] as $locale) {

                if ($locale == 'en') {
                    $status['title'] = trans($statusTrans, [], $locale);
                    $statusCreated = AppointmentStatusTranslation::where("title",$status['title'])->where("locale",$locale)->first();
                    if(!isset($statusCreated->id))
                        $appointmentStatus = AppointmentStatus::create($status);
                } else {
                    $title = trans($statusTrans, [], $locale);
                    $statusCreated = AppointmentStatusTranslation::where("title",$title)->where("locale",$locale)->first();
                    if(!isset($statusCreated->id)){
                        $appointmentStatus->translateOrNew($locale)->title = $title;
                        $appointmentStatus->save();
                    }

                }

            }//End Foreach
        }//End Foreach
    }
}
