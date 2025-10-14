<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateFarforrentCatalogSubcategory2 extends Migration
{
    public function up()
    {
        Schema::rename('farforrent_catalog_subcategories', 'farforrent_catalog_subcategory');
    }
    
    public function down()
    {
        Schema::rename('farforrent_catalog_subcategory', 'farforrent_catalog_subcategories');
    }
}
