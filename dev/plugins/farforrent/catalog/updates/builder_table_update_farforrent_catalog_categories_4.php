<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogCategories4 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_categories', function($table)
        {
            $table->integer('model');
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_categories', function($table)
        {
            $table->dropColumn('model');
        });
    }
}
