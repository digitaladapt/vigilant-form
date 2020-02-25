<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubmissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ip_address_id');
            $table->unsignedBigInteger('submission_type_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->uuid('uuid')->unique();
            $table->string('device', 10); /* enum */
            $table->string('platform', 25);
            $table->string('browser', 25);
            $table->boolean('honeypot');
            $table->time('duration', 4);
            $table->integer('score')->default(-1);
            $table->string('grade', 10)->default('ungraded'); /* enum */
            $table->boolean('synced')->default(false);
            $table->text('message')->nullable();
            $table->dateTime('created_at', 4)->useCurrent();
            $table->dateTime('updated_at', 4)->useCurrent();

            $table->index('grade');
            $table->index('synced');

            $table->foreign('ip_address_id')->references('id')->on('ip_addresses')
                ->onUpdate('restrict')->onDelete('restrict');

            $table->foreign('submission_type_id')->references('id')->on('submission_types')
                ->onUpdate('restrict')->onDelete('restrict');

            $table->foreign('account_id')->references('id')->on('accounts')
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
        Schema::dropIfExists('submissions');
    }
}
