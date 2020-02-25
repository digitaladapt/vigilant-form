<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubmissionLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('submission_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('submission_id');
            $table->string('type', 10); /* enum */
            $table->string('url', 1000)->nullable();
            $table->dateTime('created_at', 4)->useCurrent();
            $table->dateTime('updated_at', 4)->useCurrent();

            $table->index('type');

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
        Schema::dropIfExists('submission_links');
    }
}
