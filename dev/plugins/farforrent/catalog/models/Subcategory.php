<?php 

namespace Farforrent\Catalog\Models;

use Model;

class Subcategory extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'farforrent_catalog_subcategories';
    public $timestamps = true;

    public $rules = [
        
    ];

    // Підкатегорія належить Категорії (FK: category_id)
    public $belongsTo = [
        'category' => [
            \Farforrent\Catalog\Models\Category::class,
            'key' => 'category_id'
        ],
    ];
    
    public function scopeInCategory($query, $parentModel)
    {
    $catId = $parentModel->category_id ?: post('Product.category'); // підхопить поточний вибір
    if ($catId) $query->where('category_id', $catId);
    }
    
}