<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogSubcategories3 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_subcategories', function($table)
        {
            $table->renameColumn('id', 'subcategory_id');
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_subcategories', function($table)
        {
            $table->renameColumn('subcategory_id', 'id');
        });
    }
}
