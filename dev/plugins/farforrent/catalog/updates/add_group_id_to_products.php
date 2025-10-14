<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddGroupIdToProducts extends Migration
{
    public function up()
    {
        Schema::table('farforrent_catalog_products', function ($t) {
            $t->integer('group_id')->unsigned()->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('farforrent_catalog_products', function ($t) {
            $t->dropColumn('group_id');
        });
    }
}
