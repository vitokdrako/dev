<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogProductGroups4 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_product_groups', function($table)
        {
            $table->dropColumn('image');
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_product_groups', function($table)
        {
            $table->string('image', 191)->nullable();
        });
    }
}
