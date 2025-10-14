<?php namespace Farforrent\Catalog\Components;

use Cms\Classes\ComponentBase;
use Farforrent\Catalog\Models\Category;

class CategoryList extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Список категорій',
            'description' => 'Відображає список категорій'
        ];
    }

    public function defineProperties()
    {
        return [
            'showTree' => [
                'title'       => 'Показати дерево',
                'description' => 'Показати категорії у вигляді дерева',
                'type'        => 'checkbox',
                'default'     => true
            ]
        ];
    }

    public function onRun()
    {
        $this->page['categories'] = $this->loadCategories();
    }

    protected function loadCategories()
    {
        $query = Category::active()->orderBy('sort_order');
        
        if ($this->property('showTree')) {
            return $query->getNested();
        }
        
        return $query->get();
    }
}