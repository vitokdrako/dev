<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogCategories2 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_categories', function($table)
        {
            $table->increments('id')->unsigned(false)->change();
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_categories', function($table)
        {
            $table->increments('id')->unsigned()->change();
        });
    }
}
