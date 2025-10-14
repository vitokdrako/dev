<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogProducts4 extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_products', function($table)
        {
            $table->string('comment', 255);
            $table->integer('rent_percent')->nullable(false)->unsigned()->default(20)->comment(null)->change();
        });
    }
    
    public function down()
    {
        Schema::table('farforrent_catalog_products', function($table)
        {
            $table->dropColumn('comment');
            $table->boolean('rent_percent')->nullable(false)->unsigned()->default(20)->comment(null)->change();
        });
    }
}
