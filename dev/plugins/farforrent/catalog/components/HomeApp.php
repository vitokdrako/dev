<?php namespace Farforrent\Catalog\Components;

use Cms\Classes\ComponentBase;
use Farforrent\Catalog\Models\Category;

class HomeApp extends ComponentBase
{
    public function componentDetails()
    {
        return ['name'=>'Home App','description'=>'App-style головна'];
    }

    public function defineProperties() { return []; }

    public function onRun()
    {
        // Беремо всі активні категорії
        $cats = Category::query()
            ->orderBy('sort_order')
            ->take(20)
            ->get();

        // 2) Пакуємо дані й витягаємо url картинки
        $this->page['topCats'] = $cats->map(function($c){
            $img = null;

            // MediaFinder: string-поле зі шляхом у медіатеці
            if (isset($c->image) && is_string($c->image) && $c->image !== '') {
                $img = $c->image; // у Twig дамо |media
            }

            // attachOne: image/icon/cover/photo → System\Models\File
            if (!$img) {
                foreach (['image','icon','cover','photo'] as $rel) {
                    if (isset($c->$rel) && $c->$rel) {
                        $f = $c->$rel;
                        if (method_exists($f, 'getThumb')) {
                            $img = $f->getThumb(160, 160, ['mode'=>'crop']);
                        } elseif (isset($f->path)) {
                            $img = $f->path;
                        }
                        if ($img) break;
                    }
                }
            }

            return [
                'id'   => $c->id,
                'name' => $c->name,
                'slug' => $c->slug ?? $c->id,
                'img'  => $img,  // може бути media-шлях або готовий url
            ];
        })->all();

        // чипи/товари поки лишаємо демо або підтягнемо пізніше з моделей
        $this->page['chips'] = ['Всі','Крісла','Столи','Дивани'];
        $this->page['newProducts'] = [
            ['name'=>'Стілець','price'=>45,'rating'=>4.9,'img'=>'assets/img/p/chair.jpg'],
            ['name'=>'Ваза','price'=>89,'rating'=>4.8,'img'=>'assets/img/p/vase.jpg'],
            ['name'=>'Диван','price'=>64,'rating'=>4.7,'img'=>'assets/img/p/sofa.jpg'],
        ];
    }
}

