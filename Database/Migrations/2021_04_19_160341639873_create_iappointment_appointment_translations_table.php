<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIappointmentAppointmentTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('iappointment__appointment_translations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            // Your translatable fields
            $table->longText('description');

            $table->integer('appointment_id')->unsigned();
            $table->string('locale')->index();
            $table->unique(['appointment_id', 'locale'],'appointment_id_locale');
            $table->foreign('appointment_id')->references('id')->on('iappointment__appointments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('iappointment__appointment_translations', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
        });
        Schema::dropIfExists('iappointment__appointment_translations');
    }
}
