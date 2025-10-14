<?php namespace Farforrent\Catalog\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Subcategories extends Controller
{
    public $implement = [      
        'Backend\Behaviors\ListController',       
        'Backend\Behaviors\FormController',
        
        ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = [
    'farforrent.catalog.access_catalog',
    'farforrent.catalog.access_subcategories',
    ];
    

    public function __construct()
    {
        parent::__construct();
        
    }
}
