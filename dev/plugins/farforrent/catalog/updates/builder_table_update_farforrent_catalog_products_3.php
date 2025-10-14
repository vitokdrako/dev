<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogProducts3 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_products', function($table)
        {
            $table->integer('quantity');
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_products', function($table)
        {
            $table->dropColumn('quantity');
        });
    }
}
