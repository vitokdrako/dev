<?php namespace Farforrent\Catalog;

use Backend;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'Catalog',
            'description' => 'Каталог з категоріями, підкатегоріями, товарами та групами товарів.',
            'author'      => 'Farforrent',
            'icon'        => 'icon-archive',
        ];
    }

    public function registerPermissions()
    {
        return [
            'farforrent.catalog.access_catalog' => [
                'tab'   => 'Catalog',
                'label' => 'Доступ до каталогу (меню)',
            ],
            'farforrent.catalog.access_products' => [
                'tab'   => 'Catalog',
                'label' => 'Доступ до товарів',
            ],
            'farforrent.catalog.access_productgroups' => [
                'tab'   => 'Catalog',
                'label' => 'Доступ до груп товарів',
            ],
            'farforrent.catalog.access_categories' => [
                'tab'   => 'Catalog',
                'label' => 'Доступ до категорій',
            ],
            'farforrent.catalog.access_subcategories' => [
                'tab'   => 'Catalog',
                'label' => 'Доступ до підкатегорій',
            ],
            'farforrent.catalog.manage_orders' => [
                'tab'   => 'Catalog',
                'label' => 'Manager API: керування замовленнями',
            ],
            'farforrent.catalog.access_manager' => [
                'tab'   => 'Catalog',
                'label' => 'Доступ до кабінету менеджера',
            ],
        ];
    }
    
    public function registerComponents()
    {
    return [
        \Farforrent\Catalog\Components\HomeApp::class => 'homeApp',
        'Farforrent\Catalog\Components\ProductList'    => 'productList',
        'Farforrent\Catalog\Components\ProductDetail'  => 'productDetail',
        'Farforrent\Catalog\Components\CategoryList'   => 'categoryList',
        'Farforrent\Catalog\Components\Cart'           => 'cart',
        'Farforrent\Catalog\Components\Checkout'       => 'checkout',
        ];
    }


    public function registerNavigation()
    {
        return [
            'catalog' => [
                'label' => 'Каталог',
                'url'   => Backend::url('farforrent/catalog/products'),
                'icon'  => 'icon-archive',
                'order' => 100,

                'sideMenu' => [
                    'products' => [
                        'label' => 'Товари',
                        'icon'  => 'icon-cube',
                        'url'   => Backend::url('farforrent/catalog/products'),
                    ],
                    'productgroups' => [
                        'label' => 'Групи товарів',
                        'icon'  => 'icon-object-group',
                        'url'   => Backend::url('farforrent/catalog/productgroups'),
                    ],
                    'categories' => [
                        'label' => 'Категорії',
                        'icon'  => 'icon-folder-open',
                        'url'   => Backend::url('farforrent/catalog/categories'),
                    ],
                    'subcategories' => [
                        'label' => 'Підкатегорії',
                        'icon'  => 'icon-folder',
                        'url'   => Backend::url('farforrent/catalog/subcategories'),
                    ],
                    'productcards' => [
                        'label'       => 'Product cards',
                        'icon'        => 'icon-th-large',
                        'url'         => Backend::url('farforrent/catalog/productcards'),
                        'permissions' => ['farforrent.catalog.*'],
                    ],
                ],
            ],
            'manager' => [
                'label' => 'Менеджер',
                'url' => Backend::url('farforrent/catalog/orders'),
                'icon' => 'icon-shopping-cart',
                'permissions' => ['farforrent.catalog.manage_orders'],
                'order' => 50,
                'sideMenu' => [
                    'orders' => [
                        'label' => 'Замовлення',
                        'icon' => 'icon-list',
                        'url' => Backend::url('farforrent/catalog/orders'),
                        'permissions' => ['farforrent.catalog.manage_orders']
                    ],
                    'payments' => [
                        'label' => 'Платежі',
                        'icon' => 'icon-money',
                        'url' => Backend::url('farforrent/catalog/payments'),
                        'permissions' => ['farforrent.catalog.manage_orders']
                    ],
                    'damages' => [
                        'label' => 'Пошкодження',
                        'icon' => 'icon-warning',
                        'url' => Backend::url('farforrent/catalog/damages'),
                        'permissions' => ['farforrent.catalog.manage_orders']
                    ]
                ]
            ]
    ];
}

    public function register()
    {
    $this->registerConsoleCommand(
        'farforrent:import-new-orders',
        \Farforrent\Catalog\Console\ImportNewOrders::class
    );
    $this->registerConsoleCommand(
        'farforrent.sync-oc-products', // дає ім'я artisan: farforrent:sync-oc-products
        \Farforrent\Catalog\Console\SyncOcProducts::class
    );
    }

public function registerSchedule($schedule)
{
    // Кожні 10 хвилин
    $schedule->command('farforrent:import-new-orders --lookback=2 --tz=Europe/Amsterdam')
        ->everyTenMinutes()
        ->withoutOverlapping();
}

    public function boot()
    {
        // 1) Alias для middleware один раз
        $router = \App::make('router');
        $router->aliasMiddleware(
            'manager.auth',
            \Farforrent\Catalog\Http\Middleware\ManagerAuth::class
        );

        // 2) Група роутів /api/manager з middleware'ами
        $router->group([
            'prefix'     => 'api/manager',
            'middleware' => ['web', 'manager.auth'],
        ], function ($router) {
            // --- СЛОВНИКИ (для фронт-форми) ---
            $router->get(
                'dict/categories',
                '\Farforrent\Catalog\Http\Controllers\Api\DictController@categories'
            );
            $router->get(
                'dict/subcategories',
                '\Farforrent\Catalog\Http\Controllers\Api\DictController@subcategories'
            );

            // --- КАРТКА (створення товару/групи з фронту) ---
            $router->post(
                'cards',
                '\Farforrent\Catalog\Http\Controllers\Api\CardsController@store'
            );

            // --- ОРДЕРИ (як робили раніше) ---
            $router->get(
                'orders',
                '\Farforrent\Catalog\Http\Controllers\Api\OrdersController@index'
            );
            $router->post(
                'orders',
                '\Farforrent\Catalog\Http\Controllers\Api\OrdersController@store'
            );
            $router->get(
                'orders/{id}',
                '\Farforrent\Catalog\Http\Controllers\Api\OrdersController@show'
            );
            $router->post(
                'orders/{id}/items',
                '\Farforrent\Catalog\Http\Controllers\Api\OrdersController@addItem'
            );
            $router->delete(
                'orders/{id}/items/{itemId}',
                '\Farforrent\Catalog\Http\Controllers\Api\OrdersController@removeItem'
            );
            $router->post(
                'orders/{id}/confirm',
                '\Farforrent\Catalog\Http\Controllers\Api\OrdersController@confirm'
            );
            $router->post(
                'orders/{id}/cancel',
                '\Farforrent\Catalog\Http\Controllers\Api\OrdersController@cancel'
            );
        });
    }
}
