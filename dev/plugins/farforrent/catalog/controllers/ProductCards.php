<?php namespace Farforrent\Catalog\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Farforrent\Catalog\Models\ProductGroup;
use Farforrent\Catalog\Models\Product;

class ProductCards extends Controller
{
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Farforrent.Catalog', 'catalog', 'productcards');

        $this->addJs('/plugins/farforrent/catalog/assets/productcards.js');
        $this->addCss('/plugins/farforrent/catalog/assets/productcards.css');
    }

    /** Хелпер: форма створення групи як FormWidget */
    protected function makeGroupFormWidget(?ProductGroup $model = null)
    {
        $model = $model ?: new ProductGroup;

        $config = $this->makeConfig('$/farforrent/catalog/controllers/productcards/_group_fields.yaml');
        $config->model     = $model;
        $config->arrayName = 'Group';
        $config->context   = 'create';
        $config->alias     = 'groupCreateForm';

        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }

    /** Хелпер: mediafinder для поля image в рядку варіанта */
    protected function makeImageWidget($model, string $arrayName, string $aliasSuffix)
    {
        $config = $this->makeConfig('$/farforrent/catalog/controllers/productcards/_image_field.yaml');
        $config->model     = $model;
        $config->arrayName = $arrayName;
        $config->alias     = 'img_'.$aliasSuffix;

        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }
    public function onSaveGroupMeta()
    {
    $id   = (int) post('groupId');
    $data = post('Group', []);

    $group = \Farforrent\Catalog\Models\ProductGroup::findOrFail($id);

    // дозволені поля
    $allowed = ['name_ua','name_en','category_id','subcategory_id','rarity',
                'rent_percent','material','color','image'];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $data)) $group->{$k} = $data[$k];
    }

    $group->save(); // твій afterSave уже перераховує варіанти

    \Flash::success('Групу оновлено.');

    // перерендерити панель + шапку картки (відсоток/застава)
    return [
        '#group-meta-'.$group->id => $this->makePartial('group_meta', ['group' => $group]),
        // якщо в шапці показуєш % і множник — онови і її:
        // '#card-'.$group->id.' .card-head' => $this->makePartial('card_head', ['group'=>$group]),
    ];
    }

    /** Список карток + форма створення нової групи */
    public function index()
    {
        $q = trim(input('q', ''));

        $groups = ProductGroup::with([
                'category', 'subcategory',
                'variants' => function ($q) { $q->orderBy('sku'); }
            ])
            ->when($q, function ($query) use ($q) {
                $query->where('name_ua', 'like', "%$q%")
                      ->orWhere('name_en', 'like', "%$q%")
                      ->orWhere('slug', 'like', "%$q%");
            })
            ->orderByDesc('id')
            ->paginate(10);

        $this->vars['groups']    = $groups;
        $this->vars['q']         = $q;
        $this->vars['groupForm'] = $this->makeGroupFormWidget(); // форма зверху
        $this->pageTitle         = 'Product cards';
    }

    /** Додати порожній рядок у ІСНУЮЧУ групу (картка) */
    public function onAddRow()
    {
        $groupId = (int) post('groupId');
        $idx     = (int) post('idx', 0);

        $group = ProductGroup::findOrFail($groupId);

        $html = $this->makePartial('variant_row', [
            'group' => $group,
            'idx'   => $idx,
        ], true);

        return ['result' => $html];
    }

    /** Додати порожній рядок у форму "Нова група" */
    public function onAddNewRow()
    {
        $idx = (int) post('idx', 0);
        $html = $this->makePartial('variant_row_new', [
            'idx' => $idx,
        ], true);
        return ['result' => $html];
    }

    /** Масове збереження рядків у картці групи */
    public function onSaveCard()
    {
        $groupId = (int) post('groupId');
        $group   = ProductGroup::findOrFail($groupId);
        $rows    = post('variants', []);

        $created = 0; $updated = 0; $errors = [];

        foreach ((array)$rows as $row) {
            try {
                $id = (int)($row['id'] ?? 0);

                /** @var Product $p */
                $p = $id
                    ? Product::where('group_id', $group->id)->findOrFail($id)
                    : new Product();

                $p->group_id = $group->id;
                $p->sku      = trim((string)($row['sku'] ?? ''));
                $p->price    = (float)($row['price'] ?? 0);
                $p->size     = trim((string)($row['size'] ?? '')) ?: null;
                $p->height   = ($row['height']   ?? '') !== '' ? $row['height']   : null;
                $p->width    = ($row['width']    ?? '') !== '' ? $row['width']    : null;
                $p->diameter = ($row['diameter'] ?? '') !== '' ? $row['diameter'] : null;
                $p->length   = ($row['length']   ?? '') !== '' ? $row['length']   : null;
                $p->status   = $row['status'] ?? ($p->status ?: 'available');
                $p->image    = $row['image']  ?? $p->image;

                $p->save();

                $id ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($created || $updated) {
            Flash::success("Збережено. Нових: {$created}, оновлено: {$updated}");
        }
        if ($errors) {
            Flash::error(implode("\n", $errors));
        }

        $group->load(['variants' => function ($q) { $q->orderBy('sku'); }]);

        return ['#card-'.$group->id => $this->makePartial('card', ['group' => $group], true)];
    }

    /** Створити НОВУ групу + одразу її варіанти */
    public function onCreateGroup()
    {
        $groupData   = post('Group', []);
        $variantsNew = post('variantsNew', []);

        // дефолти, щоб валідатор не бурчав
        if (empty($groupData['rent_percent'])) $groupData['rent_percent'] = 20;
        if (empty($groupData['rarity']))       $groupData['rarity']       = 'regular';

        // створюємо групу
        $group = new ProductGroup();
        $group->fill($groupData);
        $group->save();

        // створюємо варіанти
        $created = 0; $errors = [];
        foreach ((array)$variantsNew as $i => $r) {
            try {
                $p = new Product();
                $p->group_id = $group->id;
                $p->sku      = trim((string)($r['sku'] ?? ''));
                $p->price    = (float)($r['price'] ?? 0);
                $p->size     = trim((string)($r['size'] ?? '')) ?: null;
                $p->height   = ($r['height']   ?? '') !== '' ? $r['height']   : null;
                $p->width    = ($r['width']    ?? '') !== '' ? $r['width']    : null;
                $p->diameter = ($r['diameter'] ?? '') !== '' ? $r['diameter'] : null;
                $p->length   = ($r['length']   ?? '') !== '' ? $r['length']   : null;
                $p->status   = $r['status'] ?? 'available';
                $p->image    = $r['image']  ?? null;

                $p->save();
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Рядок #".($i+1).": ".$e->getMessage();
            }
        }

        $group->load(['variants' => function ($q) { $q->orderBy('sku'); }]);

        $msg = "Групу створено (ID {$group->id}). Додано варіантів: {$created}.";
        if ($errors) {
            Flash::error(implode("\n", $errors));
        }
        Flash::success($msg);

        // Повертаємо html нової картки та очищену форму створення
        return [
            'card' => $this->makePartial('card', ['group' => $group], true),
            'form' => $this->makePartial('create_group', [
                'groupForm' => $this->makeGroupFormWidget(new ProductGroup)
            ], true),
        ];
    }

    /** Видалити один варіант з існуючої групи */
    public function onDeleteVariant()
    {
        $groupId = (int) post('groupId');
        $id      = (int) post('id');

        if ($id) {
            Product::where('group_id', $groupId)
                ->where('id', $id)
                ->delete();
        }

        Flash::success('Варіант видалено');
        return;
    }
}
