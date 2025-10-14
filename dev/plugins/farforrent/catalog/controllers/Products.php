<?php 

namespace Farforrent\Catalog\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Products extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        
    ];

    public $listConfig   = 'config_list.yaml';
    public $formConfig   = 'config_form.yaml';
    public $requiredPermissions = [
    'farforrent.catalog.access_catalog',
    'farforrent.catalog.access_products',
    ];
    

    public function __construct()
    {
        parent::__construct();
        \BackendMenu::setContext('Farforrent.Catalog', 'catalog', 'products');
    }
    
    
}