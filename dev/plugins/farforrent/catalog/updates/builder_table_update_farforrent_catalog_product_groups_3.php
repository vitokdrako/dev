<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogProductGroups3 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_product_groups', function($table)
        {
            $table->integer('rent_percent')->unsigned()->change();
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_product_groups', function($table)
        {
            $table->integer('rent_percent')->unsigned(false)->change();
        });
    }
}
