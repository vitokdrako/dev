<?php

namespace Farforrent\Catalog\Models;

use Model;
use Illuminate\Support\Str;
use Farforrent\Catalog\Models\Category;
use Farforrent\Catalog\Models\Subcategory;

class Product extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Purgeable;

    /** База */
    public $table = 'farforrent_catalog_products';
    public $timestamps = true;
    protected $dates = ['deleted_at'];
    
    public $attachOne = [
        // було щось типу 'Farforrent\Catalog\Models\File' — це помилка
        'image' => ['System\Models\File'],
    ];
    /** Віртуальні/службові поля */
    protected $purgeable = ['related_skus', 'regenerate_sku'];
    protected $relatedSkusBuffer = null;
    protected $appends = ['related_skus'];

    /** Валідація (group_id тепер НЕ обов’язковий) */
    public $rules = [
    'name_ua'        => 'required',
    'sku'            => 'required|unique:farforrent_catalog_products,sku', // unique тільки для sku
    'price'          => 'required|numeric|min:0',
    'quantity'       => 'nullable|integer|min:0',

    'group_id'       => 'nullable|integer|exists:farforrent_catalog_product_groups,id',
    'category_id'    => 'nullable|integer|exists:farforrent_catalog_categories,id',
    'subcategory_id' => 'nullable|integer|exists:farforrent_catalog_subcategories,id',

    'status'         => 'in:available,rented,damaged,in_progress,for_sale',
];


    /** Зв’язки */
    public $belongsTo = [
        'category'    => [\Farforrent\Catalog\Models\Category::class,    'key' => 'category_id'],
        'subcategory' => [\Farforrent\Catalog\Models\Subcategory::class, 'key' => 'subcategory_id'],
        'group'       => [\Farforrent\Catalog\Models\ProductGroup::class,'key' => 'group_id'],
    ];

    public $belongsToMany = [
        'related_products' => [
            self::class,
            'table'      => 'farforrent_catalog_product_related',
            'key'        => 'product_id',
            'otherKey'   => 'related_id',
            'conditions' => 'farforrent_catalog_products.deleted_at IS NULL',
        ],
    ];

    /** --------- Хелпери для SKU --------- */

    /** Префікс із категорії/підкатегорії: 2 літери (X = невідомо) */
    protected function makeSkuPrefix(): string
    {
    $catName = $this->category->name_en
        ?? $this->category->name_ua
        ?? $this->category->slug
        ?? null;

    $subName = $this->subcategory->name_en
        ?? $this->subcategory->name_ua
        ?? $this->subcategory->slug
        ?? null;

    $c = $catName ? strtoupper(Str::substr(Str::slug($catName), 0, 1)) : 'X';
    $s = $subName ? strtoupper(Str::substr(Str::slug($subName), 0, 1)) : 'X';
    return $c.$s;
    }


    /** Генерація унікального SKU у форматі <PREFIX><####> (CS1000, CS1001, …) */
protected function generateUniqueSku(string $prefix): string
{
    // нормалізуємо префікс
    $prefix = strtoupper(preg_replace('/[^A-Z\-]/', '', (string)$prefix) ?: 'XX');

    // знайдемо максимальний числовий хвіст після префікса
    $len = strlen($prefix);
    $max = self::where('sku', 'like', $prefix.'%')
        ->selectRaw("MAX(CAST(SUBSTRING(sku, ".($len+1).") AS UNSIGNED)) as m")
        ->value('m');

    $n = ((int)$max) + 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/** Якщо SKU порожній — згенерувати */
protected function ensureSkuIfEmpty(): void
{
    if (!empty($this->sku)) return;
    $this->sku = $this->generateUniqueSku($this->makeSkuPrefix());
}

    /** --------- Скоупи/опції --------- */

    public function scopeSkuPrefix($query, $value)
    {
        $value = trim((string)$value);
        if ($value === '') return $query;
        return $query->where($this->getTable().'.sku', 'like', $value.'%');
    }

    /** Залежний дропдаун підкатегорій */
    public function getSubcategoryIdOptions()
    {
        $catId = $this->category_id
            ?? post('Product.category')
            ?? post('Product[category]');

        if (!$catId) return [];

        return Subcategory::where('category_id', $catId)
            ->orderBy('name_ua')
            ->pluck('name_ua', 'id')
            ->toArray();
    }

    /** --------- Життєвий цикл --------- */

    public function beforeValidate()
{
    if ($this->exists && $this->id) {
        $this->rules['sku'] = 'required|unique:farforrent_catalog_products,sku,' . $this->id;
    }

    $this->ensureDefaults();
    $this->computePrices();
    $this->ensureBarcode();
    $this->ensureStatus();

    // 1) Спершу — наслідування від групи
    if ($this->group) {
        $this->category_id    = $this->group->category_id;
        $this->subcategory_id = $this->group->subcategory_id;

        if (!$this->material && $this->group->material) $this->material = $this->group->material;
        if (!$this->color    && $this->group->color)    $this->color    = $this->group->color;
    }

    // 2) Лише тепер — генерація SKU (бо вже є category/subcategory)
    $this->ensureSkuIfEmpty();

    // Перевірки
    if ($this->subcategory_id && $this->category_id) {
        $ok = Subcategory::where('id', $this->subcategory_id)
            ->where('category_id', $this->category_id)
            ->exists();
        if (!$ok) {
            throw new \October\Rain\Exception\ValidationException([
                'subcategory_id' => 'Підкатегорія не належить вибраній категорії.',
            ]);
        }
    }

    $hasSize = (string)($this->size ?? '') !== '';
    $hasDims = ($this->height || $this->width || $this->diameter || $this->length);
    if (!$hasSize && !$hasDims) {
        throw new \October\Rain\Exception\ValidationException([
            'size' => 'Вкажіть "size" або один із габаритів (height/width/diameter/length).',
        ]);
    }
}

    public function beforeSave()
    {
        $this->ensureDefaults();
        $this->computePrices();
        $this->ensureBarcode();
        $this->ensureStatus();

        // Якщо змінили category — перевіряємо валідність subcategory
        if ($this->isDirty('category_id') && $this->subcategory_id) {
            $stillValid = Subcategory::where('id', $this->subcategory_id)
                ->where('category_id', $this->category_id)
                ->exists();
            if (!$stillValid) {
                $this->subcategory_id = null;
            }
        }

        // Регенерація SKU за бажанням із форми
        if (post('Product.regenerate_sku')) {
            $this->sku = null;
            $this->ensureSkuIfEmpty();
        }
    }

    public function afterSave()
    {
        parent::afterSave();

        // Синхронізація related_products по введених SKU
        $skus = $this->relatedSkusBuffer ?? post('Product.related_skus') ?? [];
        if (is_string($skus)) {
            $skus = array_filter(array_map('trim', explode(',', $skus)));
        } elseif (is_array($skus)) {
            $skus = array_filter(array_map('trim', $skus));
        }

        if (!empty($skus)) {
            $ids = self::whereIn('sku', $skus)
                ->where('id', '!=', $this->id)
                ->pluck('id')->all();

            $this->related_products()->sync($ids);

            // взаємні зв’язки (за потреби)
            if (!empty($ids)) {
                $related = self::whereIn('id', $ids)->get();
                foreach ($related as $item) {
                    $item->related_products()->syncWithoutDetaching([$this->id]);
                }
            }
        }

        $this->relatedSkusBuffer = null; // очистити буфер
    }

    /** --------- Бізнес-логіка --------- */

    /**
     * Єдина точка підрахунку: якщо є група — беремо її rent_percent/rarity,
     * інакше — користуємось власними полями (rent_percent, rarity) або дефолтами.
     */
    protected function computePrices(): void
    {
        $price = (float) ($this->price ?? 0);

        if ($this->group) {
            $percent = (int) ($this->group->rent_percent ?? 10);
            $rateMap = ['regular'=>1.0,'rare'=>2.0,'vintage'=>3.0];
            $rate    = $rateMap[$this->group->rarity ?? 'regular'] ?? 1.0;
        } else {
            $allowed = [5,10,15,20,25,30];
            $percent = (int) ($this->rent_percent ?? 20);
            if (!in_array($percent, $allowed, true)) $percent = 20;

            $rateMap = ['regular'=>1.0,'rare'=>2.0,'vintage'=>3.0];
            $rate    = $rateMap[$this->rarity ?? 'regular'] ?? 1.0;
        }

        $this->rent_price = $price > 0 ? (int) round($price * ($percent / 100)) : 0;
        $this->deposit    = $price > 0 ? (int) round($price * $rate) : 0;
    }

    public function getRentPercentOptions()
    {
        // Для форм, якщо товар без групи
        return [5=>'5%',10=>'10%',15=>'15%',20=>'20%',25=>'25%',30=>'30%'];
    }

    /** Генерація barcode: якщо є group_id — включимо його, інакше PRD-… */
    protected function ensureBarcode(): void
    {
        if (!empty($this->barcode)) return;

        $prefix = $this->group_id ? ('GRP'.$this->group_id.'-') : 'PRD-';
        $tail   = $this->sku ?: strtoupper(substr(uniqid('', true), -6));
        $this->barcode = $prefix . $tail;
    }

    protected function ensureDefaults(): void
    {
        if ($this->damage_price === null || $this->damage_price === '') {
            $this->damage_price = 0;
        }
        if (empty($this->rent_percent)) {
            $this->rent_percent = 20; // для випадку без групи
        }
    }

    protected function ensureStatus(): void
    {
        if (empty($this->status)) {
            $this->status = 'available';
        }
    }

    /** --------- Віртуальне поле related_skus --------- */

    public function getRelatedSkusAttribute()
    {
        if (!$this->exists) return [];
        return $this->related_products()->pluck('sku')->toArray();
    }

    public function setRelatedSkusAttribute($value)
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }
        $this->relatedSkusBuffer = is_array($value) ? $value : [];
    }

    /** Хелпер для фронту */
    public function getRelatedForFrontend($limit = 8)
    {
        return $this->related_products()
            ->with(['category', 'subcategory'])
            ->take($limit)
            ->get();
    }
}
