<?php

namespace Modules\Iappointment\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class IappointmentDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $this->call(IappointmentModuleTableSeeder::class);
        $this->call(AppointmentStatusTableSeeder::class);
    }
}
