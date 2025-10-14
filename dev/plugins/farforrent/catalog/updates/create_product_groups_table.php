<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateProductGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('farforrent_catalog_product_groups', function ($t) {
            $t->increments('id');
            $t->integer('category_id')->unsigned()->nullable()->index();
            $t->integer('subcategory_id')->unsigned()->nullable()->index();
            $t->string('name_ua');
            $t->string('name_en')->nullable();
            $t->string('material')->nullable();
            $t->string('color')->nullable();
            $t->string('image')->nullable();
            $t->string('rarity')->default('regular'); // regular|rare|vintage
            $t->string('slug')->nullable()->unique();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('farforrent_catalog_product_groups');
    }
}
