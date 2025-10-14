<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogProductGroups extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_product_groups', function($table)
        {
            $table->integer('rent_percent')->unsigned();
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_product_groups', function($table)
        {
            $table->dropColumn('rent_percent');
        });
    }
}
