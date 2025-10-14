<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateProductRelatedTable extends Migration
{
    public function up()
    {
        Schema::create('farforrent_catalog_product_related', function($table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('related_id');

            // унікальність пари
            $table->unique(['product_id', 'related_id']);

            // захист від зв'язку на самого себе
            $table->index('product_id');
            $table->index('related_id');

            $table->foreign('product_id')
                ->references('id')->on('farforrent_catalog_products')
                ->onDelete('cascade');

            $table->foreign('related_id')
                ->references('id')->on('farforrent_catalog_products')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('farforrent_catalog_product_related');
    }
}