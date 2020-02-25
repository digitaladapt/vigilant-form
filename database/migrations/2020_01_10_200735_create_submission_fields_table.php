<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubmissionFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('submission_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('submission_id');
            $table->string('field', 50);
            $table->string('input', 255)->nullable();
            $table->dateTime('created_at', 4)->useCurrent();
            $table->dateTime('updated_at', 4)->useCurrent();

            $table->index('field');

            $table->foreign('submission_id')->references('id')->on('submissions')
                ->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('submission_fields');
    }
}
