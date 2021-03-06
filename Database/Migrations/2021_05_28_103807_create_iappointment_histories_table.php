<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIappointmentHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('iappointment__appointment_assign_history', function (Blueprint $table) {
            $table->id();

            $table->integer('appointment_id')->unsigned();
            $table->foreign('appointment_id','appointment_hist_id_foreign')->references('id')->on('iappointment__appointments')->onDelete('restrict');

            $table->integer('assigned_to')->unsigned()->nullable();
            $table->foreign('assigned_to','assigned_to_hist_foreign')->references('id')->on(config('auth.table', 'users'))->onDelete('restrict');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('iappointment__appointment_assign_history');
    }
}
