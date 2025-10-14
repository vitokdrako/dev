<?php namespace Farforrent\Catalog\Updates;

use DB;
use Schema;
use October\Rain\Database\Updates\Migration;

class FixProductRelationsAfterManualIDChanges extends Migration
{
    public function up()
    {
        // 1) Підтягуємо category_id із підкатегорії (де є subcategory_id)
        DB::statement("
            UPDATE farforrent_catalog_products p
            JOIN farforrent_catalog_subcategories s ON s.id = p.subcategory_id
            SET p.category_id = s.category_id
            WHERE p.subcategory_id IS NOT NULL
        ");

        // 2) Обнуляємо биті category_id (неіснуючі категорії)
        DB::statement("
            UPDATE farforrent_catalog_products p
            LEFT JOIN farforrent_catalog_categories c ON c.id = p.category_id
            SET p.category_id = NULL
            WHERE c.id IS NULL
        ");

        // 3) Обнуляємо биті subcategory_id (неіснуючі підкатегорії)
        DB::statement("
            UPDATE farforrent_catalog_products p
            LEFT JOIN farforrent_catalog_subcategories s ON s.id = p.subcategory_id
            SET p.subcategory_id = NULL
            WHERE s.id IS NULL
        ");

        // 4) Дефолтний статус для порожніх
        DB::table('farforrent_catalog_products')
            ->whereNull('status')
            ->update(['status' => 'available']);
    }

    public function down()
    {
        // Без відкату: це ремонт даних.
    }
}
