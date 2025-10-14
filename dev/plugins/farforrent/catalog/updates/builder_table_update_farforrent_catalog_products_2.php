<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogProducts2 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_products', function($table)
        {
            $table->renameColumn('model', 'sku');
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_products', function($table)
        {
            $table->renameColumn('sku', 'model');
        });
    }
}
