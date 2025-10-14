<?php namespace Farforrent\Catalog\Components;

use Cms\Classes\ComponentBase;
use Farforrent\Catalog\Models\Product;

/**
 * ProductList Component
 */
class ProductList extends ComponentBase
{
    public $products;

    public function componentDetails()
    {
        return [
            'name' => 'Список товарів',
            'description' => 'Відображає список товарів'
        ];
    }

    public function defineProperties()
    {
        return [
            'categoryFilter' => [
                'title' => 'Категорія',
                'description' => 'Фільтр по категорії (slug)',
                'type' => 'string',
                'default' => ''
            ],
            'resultsPerPage' => [
                'title' => 'Результатів на сторінці',
                'description' => 'Кількість товарів на сторінці',
                'type' => 'string',
                'default' => '8',
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => 'Тільки цілі числа'
            ],
            'sortOrder' => [
                'title' => 'Сортування',
                'description' => 'Порядок сортування',
                'type' => 'dropdown',
                'default' => 'name_asc',
                'options' => [
                    'name_asc' => 'За назвою (А-Я)',
                    'name_desc' => 'За назвою (Я-А)',
                    'price_asc' => 'За ціною (зростання)',
                    'price_desc' => 'За ціною (спадання)',
                    'sort_order' => 'За порядком сортування'
                ]
            ]
        ];
    }

    public function onRun()
    {
        $this->products = $this->loadProducts();
    }

    protected function loadProducts()
    {
        // Базовий запит з завантаженням відносин
        $query = Product::with(['categories', 'featured_image'])
            ->where('is_active', 1);

        // Фільтр по категорії якщо вказано
        if ($categorySlug = $this->property('categoryFilter')) {
            $query->whereHas('categories', function($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Сортування
        $sortOrder = $this->property('sortOrder', 'name_asc');
        
        switch ($sortOrder) {
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'price_asc':
                $query->orderBy('price_per_day', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price_per_day', 'desc');
                break;
            case 'sort_order':
            default:
                $query->orderBy('sort_order', 'asc')
                      ->orderBy('name', 'asc');
                break;
        }

        // Пагінація
        return $query->paginate($this->property('resultsPerPage', 8));
    }

    /**
     * AJAX handler для фільтрації
     */
    public function onFilter()
    {
        $this->products = $this->loadProducts();
        
        return [
            '#product-list' => $this->renderPartial('@default')
        ];
    }
}