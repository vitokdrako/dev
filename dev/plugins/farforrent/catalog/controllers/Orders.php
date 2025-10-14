<?php namespace Farforrent\Catalog\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Farforrent\Catalog\Models\Order;

class Orders extends Controller
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
        BackendMenu::setContext('Farforrent.Catalog', 'manager', 'orders');
    }

    /**
     * Extend list query
     */
    public function listExtendQuery($query)
    {
        // Сортування за датою створення
        $query->orderBy('created_at', 'desc');
    }

    /**
     * Extend form fields
     */
    public function formExtendFields($form)
    {
        // Можна додати кастомні поля
    }
}