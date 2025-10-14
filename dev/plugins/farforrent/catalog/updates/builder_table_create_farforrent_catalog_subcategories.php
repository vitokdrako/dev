<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateFarforrentCatalogSubcategories extends Migration
{
    public function up()
    {
        Schema::create('farforrent_catalog_subcategories', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('category_id')->unsigned();
            $table->string('name_ua', 255);
            $table->string('name_en', 255)->nullable();
            $table->string('slug', 255)->nullable();
            $table->string('image', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('farforrent_catalog_subcategories');
    }
}
