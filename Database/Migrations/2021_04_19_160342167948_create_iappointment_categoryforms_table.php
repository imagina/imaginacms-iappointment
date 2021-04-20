<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIappointmentCategoryFormsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('iappointment__category_forms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            // Your fields
            $table->integer('category_id')->unsigned();
            $table->foreign('category_id', 'ap_category_form_foreign')->references('id')->on('iappointment__categories')->onDelete('restrict');
            $table->integer('form_id')->unsigned();
            $table->foreign('form_id', 'ap_category_form_foreign_2')->references('id')->on('iforms__forms')->onDelete('restrict');
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
        Schema::table('iappointment__category_forms', function (Blueprint $table) {
            $table->dropForeign('ap_category_form_foreign');
            $table->dropForeign('ap_category_form_foreign_2');
        });
        Schema::dropIfExists('iappointment__category_forms');
    }
}
