<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogProducts7 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_products', function($table)
        {
            $table->text('company_name');
            $table->string('company_sku', 255);
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_products', function($table)
        {
            $table->dropColumn('company_name');
            $table->dropColumn('company_sku');
        });
    }
}
