<?php namespace Farforrent\Catalog\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use Input;

class Manager extends Controller
{
    public $requiredPermissions = [
        'farforrent.catalog.manage_orders'
    ];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Farforrent.Catalog', 'manager', 'dashboard');
    }

    /**
     * Manager Dashboard - Main screen with order cards
     */
    public function index()
    {
        $this->pageTitle = 'Кабінет менеджера FarforRent';
        
        // Тимчасові дані для тестування
        $this->vars['newOrders'] = [];
        $this->vars['readyForIssue'] = [];
        $this->vars['returnsDue'] = [];
        $this->vars['pendingReturns'] = [];

        // Тестова статистика
        $this->vars['todayStats'] = [
            'issues_today' => 5,
            'returns_today' => 3,
            'total_revenue_today' => 15420,
        ];
    }

    /**
     * Create New Order - тимчасово заглушка
     */
    public function createOrder()
    {
        $this->pageTitle = 'Створити замовлення';
        Flash::info('Функція створення замовлення буде додана пізніше');
        return redirect()->back();
    }

    /**
     * Issue Order - тимчасово заглушка
     */
    public function issueOrder($orderId = null)
    {
        $this->pageTitle = 'Видача замовлення';
        Flash::info('Функція видачі замовлення буде додана пізніше');
        return redirect()->back();
    }

    /**
     * Process Return - тимчасово заглушка
     */
    public function processReturn($orderId = null)
    {
        $this->pageTitle = 'Обробка повернення';
        Flash::info('Функція обробки повернення буде додана пізніше');
        return redirect()->back();
    }
}