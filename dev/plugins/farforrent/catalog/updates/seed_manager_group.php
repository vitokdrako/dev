<?php namespace Farforrent\Catalog\Updates;

use Schema;
use October\Rain\Database\Updates\Seeder;
use RainLab\User\Models\UserGroup;

class SeedManagerGroup extends Seeder
{
    public function run()
    {
        if (!class_exists(UserGroup::class)) return;

        if (!UserGroup::where('code','manager')->exists()) {
            UserGroup::create([
                'name' => 'Manager',
                'code' => 'manager',
                'description' => 'Доступ до менеджерського кабінету'
            ]);
        }
    }
}
