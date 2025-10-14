<?php namespace Farforrent\Catalog\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Db;
use System\Models\File;
use Farforrent\Catalog\Models\ProductGroup;
use Farforrent\Catalog\Models\Product;

class CardsController extends Controller
{
    /**
     * GET /api/manager/lookup?by=id|sku|barcode&value=...
     */
    public function lookup(Request $r)
    {
        $by    = $r->query('by', 'id');      // id | sku | barcode
        $value = trim((string)$r->query('value', ''));

        if ($value === '') {
            return response()->json(['message' => 'value is required'], 422);
        }

        $q = Product::query();
        if ($by === 'sku')        $q->where('sku', $value);
        elseif ($by === 'barcode') $q->where('barcode', $value);
        else                       $q->where('id', (int)$value);

        $p = $q->first();
        if (!$p) return response()->json(['message' => 'Not found'], 404);

        return response()->json([
            'id'        => $p->id,
            'sku'       => $p->sku,
            'name_ua'   => $p->name_ua,
            'size'      => $p->size,
            'price'     => $p->price,
            'height'    => $p->height,
            'width'     => $p->width,
            'diameter'  => $p->diameter,
            'length'    => $p->length,
            'status'    => $p->status,
            'quantity'  => $p->quantity,
            'image_url' => $p->image ? $p->image->getThumb(200, 200, ['mode' => 'crop']) : null,
            'category_id'    => $p->category_id,
            'subcategory_id' => $p->subcategory_id,
            'material'       => $p->material,
            'color'          => $p->color,
        ]);
    }

    /**
     * GET /api/manager/sku/next?prefix=CS&count=3
     */
    public function nextSku(Request $r)
    {
        $prefix = strtoupper(preg_replace('/[^A-Z\-]/', '', (string)$r->query('prefix', 'XX')));
        $count  = max(1, min((int)$r->query('count', 1), 100)); // 1..100

        $len = strlen($prefix);
        $max = Product::where('sku', 'like', $prefix.'%')
            ->selectRaw("MAX(CAST(SUBSTRING(sku, ".($len+1).") AS UNSIGNED)) as m")
            ->value('m');

        $start = (int)$max + 1;
        $skus = [];
        for ($i = 0; $i < $count; $i++) {
            $skus[] = $prefix . str_pad((string)($start + $i), 4, '0', STR_PAD_LEFT);
        }

        return response()->json(['start' => $start, 'skus' => $skus]);
    }

    /**
     * POST /api/manager/cards   (multipart/form-data або payload JSON)
     */
    public function store(Request $r)
    {
        try {
            // 1) payload(JSON) або form-data
            $payload = $r->input('payload');
            $data    = $payload ? json_decode($payload, true) : $r->all();

            // 2) Нормалізація group (і group[...], і плоскі ключі)
            $flatKeys = [
                'name_ua','name_en','category_id','subcategory_id',
                'material','color','rarity','rent_percent',
            ];

            $groupData = [];
            if (isset($data['group']) && is_array($data['group'])) {
                $groupData = $data['group'];
            }
            foreach ($flatKeys as $k) {
                if (!array_key_exists($k, $groupData) && array_key_exists($k, $data)) {
                    $groupData[$k] = $data[$k];
                }
            }
            if (!isset($groupData['name_ua']) && isset($groupData['nameUa'])) {
                $groupData['name_ua'] = $groupData['nameUa'];
            }

            // 3) Варіанти
            $variants = $data['variants'] ?? [];
            if (!is_array($variants)) $variants = [];

            // 4) Валідація
            $v = Validator::make(
                [
                    'name_ua'        => $groupData['name_ua']        ?? null,
                    'name_en'        => $groupData['name_en']        ?? null,
                    'category_id'    => $groupData['category_id']    ?? null,
                    'subcategory_id' => $groupData['subcategory_id'] ?? null,
                    'material'       => $groupData['material']       ?? null,
                    'color'          => $groupData['color']          ?? null,
                    'rarity'         => $groupData['rarity']         ?? null,
                    'rent_percent'   => $groupData['rent_percent']   ?? null,
                    'variants'       => $variants,
                ],
                [
                    'name_ua'        => 'required|string|max:255',
                    'name_en'        => 'nullable|string|max:255',
                    'category_id'    => 'required|integer',
                    'subcategory_id' => 'required|integer',
                    'material'       => 'nullable|string|max:255',
                    'color'          => 'nullable|string|max:255',
                    'rarity'         => 'nullable|in:regular,rare,vintage',
                    'rent_percent'   => 'nullable|integer|min:0|max:100',

                    'variants'                 => 'nullable|array|min:1',
                    'variants.*.name_ua'       => 'required|string|max:255',
                    'variants.*.size'          => 'nullable|string|max:64',
                    'variants.*.sku'           => 'nullable|string|max:64',
                    'variants.*.price'         => 'nullable|numeric|min:0',
                    'variants.*.status'        => 'nullable|string|max:32',
                    'variants.*.height'        => 'nullable|numeric|min:0',
                    'variants.*.width'         => 'nullable|numeric|min:0',
                    'variants.*.diameter'      => 'nullable|numeric|min:0',
                    'variants.*.length'        => 'nullable|numeric|min:0',
                    'variants.*.quantity'      => 'nullable|integer|min:0',
                    'variants.*.image'         => 'nullable|file|image|max:5120',
                    'variants.*.existing_id'   => 'nullable|integer|min:1',
                    'variants.*.id'            => 'nullable|integer|min:1',
                ],
                [],
                [
                    'name_ua'            => 'Назва (UA)',
                    'variants.*.name_ua' => 'Назва варіанту',
                ]
            );
            $v->validate();

            $group = null;

            Db::transaction(function () use ($r, $groupData, $variants, &$group) {

                // 5) Створити групу
                $group = new ProductGroup();
                $group->fill([
                    'name_ua'        => trim($groupData['name_ua'] ?? ''),
                    'name_en'        => trim($groupData['name_en'] ?? ''),
                    'category_id'    => (int)($groupData['category_id'] ?? 0),
                    'subcategory_id' => (int)($groupData['subcategory_id'] ?? 0),
                    'material'       => trim($groupData['material'] ?? ''),
                    'color'          => trim($groupData['color'] ?? ''),
                    'rarity'         => $groupData['rarity'] ?? 'regular',
                    'rent_percent'   => (int)($groupData['rent_percent'] ?? 20),
                ]);
                $group->save();

                // 6) Фото групи (опційно)
                if ($r->hasFile('group_image')) {
                    $file = new File();
                    $file->data = $r->file('group_image');
                    $file->is_public = true;
                    $file->save();
                    $group->image()->add($file);
                }

                // 7) Варіанти
                foreach ($variants as $i => $row) {

                    // --- Режим "прив’язати існуючий" ---
                    $existingId = (int)($row['existing_id'] ?? $row['id'] ?? 0);
                    if ($existingId) {
                        $p = Product::find($existingId);
                        if (!$p) continue;

                        // оновлюємо лише те, що прийшло
                        $fill = [
                            'sku'      => $row['sku']      ?? $p->sku,
                            'name_ua'  => $row['name_ua']  ?? $p->name_ua,
                            'size'     => $row['size']     ?? $p->size,
                            'price'    => array_key_exists('price',    $row) ? (float)$row['price']    : $p->price,
                            'height'   => array_key_exists('height',   $row) ? (float)$row['height']   : $p->height,
                            'width'    => array_key_exists('width',    $row) ? (float)$row['width']    : $p->width,
                            'diameter' => array_key_exists('diameter', $row) ? (float)$row['diameter'] : $p->diameter,
                            'length'   => array_key_exists('length',   $row) ? (float)$row['length']   : $p->length,
                            'status'   => $row['status']   ?? $p->status,
                            'quantity' => array_key_exists('quantity', $row) ? (int)$row['quantity']   : $p->quantity,
                        ];

                        // Прив’язуємо і наслідуємо від групи
                        $p->group_id       = $group->id;
                        $p->category_id    = $group->category_id ?: $p->category_id;
                        $p->subcategory_id = $group->subcategory_id ?: $p->subcategory_id;
                        if (!$p->material && $group->material) $p->material = $group->material;
                        if (!$p->color    && $group->color)    $p->color    = $group->color;

                        $p->fill($fill);
                        $p->save();

                        // Заміна фото, якщо завантажили
                        if ($r->hasFile("variants.$i.image")) {
                            $f = new File();
                            $f->data = $r->file("variants.$i.image");
                            $f->is_public = true;
                            $f->save();
                            $p->image()->add($f); // attachOne автоматично замінить
                        }
                        continue;
                    }

                    // --- Створення нового ---
                    $hasContent = !empty($row['sku']) || !empty($row['price']) || !empty($row['name_ua']);
                    if (!$hasContent) continue;

                    $p = new Product();
                    $p->group_id       = $group->id;
                    $p->name_ua        = trim($row['name_ua'] ?? '');
                    $p->quantity       = (int)($row['quantity'] ?? 0);
                    $p->sku            = trim($row['sku'] ?? '');
                    $p->size           = trim($row['size'] ?? '');
                    $p->price          = (float)($row['price'] ?? 0);
                    $p->height         = (float)($row['height'] ?? 0);
                    $p->width          = (float)($row['width'] ?? 0);
                    $p->diameter       = (float)($row['diameter'] ?? 0);
                    $p->length         = (float)($row['length'] ?? 0);
                    $p->status         = $row['status'] ?? 'available';

                    // від групи
                    $p->category_id    = $group->category_id;
                    $p->subcategory_id = $group->subcategory_id;
                    $p->color          = $row['color']    ?? $group->color;
                    $p->material       = $row['material'] ?? $group->material;

                    // підрахунки
                    $percent = (int)$group->rent_percent;
                    $rateMap = ['regular'=>1.0,'rare'=>2.0,'vintage'=>3.0];
                    $rate    = $rateMap[$group->rarity] ?? 1.0;
                    $p->rent_price = (int) round($p->price * ($percent / 100));
                    $p->deposit    = (int) round($p->price * $rate);

                    $p->save();

                    // Фото нового варіанта
                    if ($r->hasFile("variants.$i.image")) {
                        $f = new File();
                        $f->data = $r->file("variants.$i.image");
                        $f->is_public = true;
                        $f->save();
                        $p->image()->add($f);
                    }
                    if ($r->hasFile("images.$i")) { // backward-compat
                        $f = new File();
                        $f->data = $r->file("images.$i");
                        $f->is_public = true;
                        $f->save();
                        $p->image()->add($f);
                    }
                }
            });

            return response()->json([
                'ok'       => 1,
                'group_id' => $group->id ?? null,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    
    // ----- Список / CRUD категорій -----

public function categoriesList()
{
    return \Farforrent\Catalog\Models\Category::orderBy('name_ua')->get(['id','name_ua']);
}

public function categorySave(\Illuminate\Http\Request $r)
{
    $id   = (int)$r->input('id', 0);
    $name = trim((string)$r->input('name_ua', ''));

    if ($name === '') return response()->json(['message'=>'name_ua is required'], 422);

    $cat = $id ? \Farforrent\Catalog\Models\Category::findOrFail($id)
               : new \Farforrent\Catalog\Models\Category();

    $cat->name_ua = $name;
    $cat->save();

    return response()->json(['id'=>$cat->id, 'name_ua'=>$cat->name_ua]);
}

public function categoryDelete($id)
{
    $cat = \Farforrent\Catalog\Models\Category::findOrFail($id);
    $cat->delete(); // каскад підкатегорій – якщо не налаштовано FK, зроби також:
    \Farforrent\Catalog\Models\Subcategory::where('category_id',$id)->delete();

    return response()->json(['ok'=>1]);
}

// ----- Список / CRUD підкатегорій -----

public function subcategoriesList(\Illuminate\Http\Request $r)
{
    $categoryId = (int)$r->query('category_id', 0);
    $q = \Farforrent\Catalog\Models\Subcategory::query();
    if ($categoryId) $q->where('category_id', $categoryId);
    return $q->orderBy('name_ua')->get(['id','category_id','name_ua']);
}

public function subcategorySave(\Illuminate\Http\Request $r)
{
    $id         = (int)$r->input('id', 0);
    $categoryId = (int)$r->input('category_id', 0);
    $name       = trim((string)$r->input('name_ua', ''));

    if (!$categoryId) return response()->json(['message'=>'category_id is required'], 422);
    if ($name === '') return response()->json(['message'=>'name_ua is required'], 422);

    $sub = $id ? \Farforrent\Catalog\Models\Subcategory::findOrFail($id)
               : new \Farforrent\Catalog\Models\Subcategory();

    $sub->category_id = $categoryId;
    $sub->name_ua     = $name;
    $sub->save();

    return response()->json(['id'=>$sub->id, 'category_id'=>$sub->category_id, 'name_ua'=>$sub->name_ua]);
}

public function subcategoryDelete($id)
{
    $sub = \Farforrent\Catalog\Models\Subcategory::findOrFail($id);
    $sub->delete();
    return response()->json(['ok'=>1]);
}

}

