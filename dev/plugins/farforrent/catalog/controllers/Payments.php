<?php namespace Farforrent\Catalog\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Payments extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['farforrent.catalog.manage_orders'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Farforrent.Catalog', 'manager', 'payments');
    }
}