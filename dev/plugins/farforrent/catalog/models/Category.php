<?php namespace Farforrent\Catalog\Models;

use Model;

class Category extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'farforrent_catalog_categories';

    public $rules = [];

    // ⬇️ Додаємо зв’язок (Категорія має багато Підкатегорій)
    public $hasMany = [
        'subcategories' => [
            \Farforrent\Catalog\Models\Subcategory::class,
            'key' => 'category_id',
        ],
    ];
}