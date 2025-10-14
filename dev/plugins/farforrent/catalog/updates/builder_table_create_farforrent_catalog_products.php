<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateFarforrentCatalogProducts extends Migration
{
    public function up()
    {
        Schema::create('farforrent_catalog_products', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('category_id')->unsigned();
            $table->integer('subcategory_id')->unsigned();
            $table->string('name_ua', 255)->nullable();
            $table->string('name_en', 255)->nullable();
            $table->string('sku')->nullable();
            $table->integer('price');
            $table->integer('rent_price');
            $table->string('rarity')->nullable();
            $table->integer('deposit');
            $table->string('material', 255)->nullable();
            $table->string('color', 255)->nullable();
            $table->string('size', 255)->nullable();
            $table->integer('damage_price');
            $table->string('image')->nullable();
            $table->string('status')->nullable();
            $table->string('barcode', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('farforrent_catalog_products');
    }
}
