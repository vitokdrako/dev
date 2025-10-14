<?php namespace Farforrent\Catalog\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class ProductGroups extends Controller
{
    public $implement = [
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.RelationController',
    ];

    public $listConfig     = 'config_list.yaml';
    public $formConfig     = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    // ⬇️ обов’язково! і має співпадати з тим, що ти видала ролі
    public $requiredPermissions = ['farforrent.catalog.manage_groups'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Farforrent.Catalog', 'catalog', 'productgroups');
    }
}
