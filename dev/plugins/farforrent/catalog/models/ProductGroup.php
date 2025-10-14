<?php namespace Farforrent\Catalog\Models;

use Model;

class ProductGroup extends Model
{
    use \October\Rain\Database\Traits\Validation;
    // Якщо у таблиці є deleted_at — можеш додати:
    // use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'farforrent_catalog_product_groups';
    public $timestamps = true;

    /* ===== Validation ===== */
    public $rules = [
        'name_ua'        => 'required',
        'slug'           => 'nullable',
        'category_id'    => 'required|integer',
        'subcategory_id' => 'required|integer',
        'rarity'         => 'required|in:regular,rare,vintage',
        
    ];
    
    public $attachOne = [
        'image' => ['System\Models\File'],
    ];
    public function getRentPercentOptions()
    {
        return [5=>'5%',10=>'10%',15=>'15%',20=>'20%',25=>'25%',30=>'30%'];
    }

    public function getRarityOptions()
    {
        return ['regular'=>'regular','rare'=>'rare','vintage'=>'vintage'];
    }

    /* ===== Relations ===== */
    public $belongsTo = [
        'category'    => [\Farforrent\Catalog\Models\Category::class,    'key' => 'category_id'],
        'subcategory' => [\Farforrent\Catalog\Models\Subcategory::class, 'key' => 'subcategory_id'],
    ];

    // Варіанти (товари) цієї групи
    public $hasMany = [
        'variants' => [\Farforrent\Catalog\Models\Product::class, 'key' => 'group_id'],
    ];

    // Додатковий довільний зв’язок (діти) через таблицю зв’язків
    public $belongsToMany = [
        'children' => [
            \Farforrent\Catalog\Models\Product::class,
            'table'    => 'farforrent_catalog_group_product',
            'key'      => 'group_id',
            'otherKey' => 'product_id',
        ],
    ];

    /* ===== Віртуальне поле для вводу SKU дітей ===== */
    protected $appends = ['children_skus'];
    protected $childrenSkusBuffer = null;

    public function getChildrenSkusAttribute()
    {
        if (!$this->exists) return '';
        $skus = $this->children()->pluck('sku')->filter()->all();
        return implode(',', $skus);
    }

    public function setChildrenSkusAttribute($value)
    {
        $this->childrenSkusBuffer = is_array($value)
            ? array_filter(array_map('trim', $value))
            : array_filter(array_map('trim', explode(',', (string) $value)));
    }

    public function getSubcategoryIdOptions()
    {
    $catId = $this->category_id
        ?? post('Group.category')
        ?? post('Group[category]');
    if (!$catId) return [];
    return \Farforrent\Catalog\Models\Subcategory::where('category_id', $catId)
        ->orderBy('name_ua')->pluck('name_ua', 'id')->toArray();
    }


    /* ===== Після збереження: 1) синхронізація children, 2) перерахунок варіантів ===== */
    public function afterSave()
    {
        parent::afterSave();

        // 1) Синхронізація children по введених SKU
        if ($this->childrenSkusBuffer !== null) {
            $ids = \Farforrent\Catalog\Models\Product::whereIn('sku', $this->childrenSkusBuffer)
                ->pluck('id')->all();
            $this->children()->sync($ids);
            $this->childrenSkusBuffer = null;
        }

        // 2) Якщо змінили ключові поля — оновлюємо всі варіанти (products) цієї групи
        if ($this->wasChanged(['category_id','subcategory_id','rarity','rent_percent','color','material'])) {

            $percent = (int) $this->rent_percent;
            $rateMap = ['regular'=>1.0,'rare'=>2.0,'vintage'=>3.0];
            $rate    = $rateMap[$this->rarity] ?? 1.0;

            foreach ($this->variants as $v) {
                // Наслідування категорій
                $v->category_id    = $this->category_id;
                $v->subcategory_id = $this->subcategory_id;

                // Якщо у варіанта порожньо — підставимо групові значення
                if (!$v->color && $this->color)       $v->color = $this->color;
                if (!$v->material && $this->material) $v->material = $this->material;

                // Перерахунок від ціни варіанта
                $price = (float) ($v->price ?? 0);
                $v->rent_price = (int) round($price * ($percent / 100));
                $v->deposit    = (int) round($price * $rate);

                $v->save();
            }
        }
    }
}
