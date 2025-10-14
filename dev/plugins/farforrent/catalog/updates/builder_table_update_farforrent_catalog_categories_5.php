<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogCategories5 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_categories', function($table)
        {
            $table->dropColumn('id');
            $table->dropColumn('model');
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_categories', function($table)
        {
            $table->increments('id');
            $table->integer('model');
        });
    }
}
