<?php namespace Void\Character\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateCharactersTable extends Migration
{

    public function up()
    {
        Schema::create('characters', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name');
            $table->string('gender')->nullable();
            $table->string('height')->nullable();
            $table->text('bio');
            $table->string('type');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('characters');
    }

}
