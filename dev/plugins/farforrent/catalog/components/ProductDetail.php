<?php namespace Farforrent\Catalog\Components;

use Cms\Classes\ComponentBase;
use Farforrent\Catalog\Models\Product;

class ProductDetail extends ComponentBase
{
    public $product;

    public function componentDetails()
    {
        return [
            'name'        => 'Деталі товару',
            'description' => 'Відображає детальну інформацію про товар'
        ];
    }

    public function defineProperties()
    {
        return [
            'slug' => [
                'title'       => 'Slug товару',
                'description' => 'URL slug товару',
                'type'        => 'string',
                'default'     => '{{ :slug }}'
            ]
        ];
    }

    public function onRun()
    {
        $this->product = $this->loadProduct();
        
        if (!$this->product) {
            return \Response::make(\View::make('cms::404'), 404);
        }
    }

    protected function loadProduct()
    {
        return Product::active()
            ->where('slug', $this->property('slug'))
            ->with('categories')
            ->first();
    }
}