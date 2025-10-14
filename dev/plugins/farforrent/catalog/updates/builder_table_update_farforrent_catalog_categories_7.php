<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogCategories7 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_categories', function($table)
        {
            $table->renameColumn('id', 'category_id');
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_categories', function($table)
        {
            $table->renameColumn('category_id', 'id');
        });
    }
}
