<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Support\ActivityLogger;
use App\Support\StockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('products/index', [
            'products' => Product::query()
                ->with(['category:id,name', 'brand:id,name', 'unit:id,name,abbreviation', 'variants:id,product_id,name'])
                ->when($request->filled('search'), function ($q) use ($request) {
                    $q->where(function ($inner) use ($request) {
                        $inner->where('name', 'like', "%{$request->search}%")
                            ->orWhere('sku', 'like', "%{$request->search}%")
                            ->orWhere('barcode', 'like', "%{$request->search}%");
                    });
                })
                ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
                ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
                ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'categories' => Category::select('id', 'name')->orderBy('name')->get(),
            'brands' => Brand::select('id', 'name')->orderBy('name')->get(),
            'units' => Unit::select('id', 'name', 'abbreviation')->orderBy('name')->get(),
            'filters' => $request->only(['search', 'category_id', 'brand_id', 'is_active']),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->safe()->except(['variants']);
        $variants = $request->input('variants', []);

        $product = Product::create($data);

        foreach ($variants as $variant) {
            $product->variants()->create($variant);
        }

        ActivityLogger::log("Created product \"{$product->name}\"", $product, 'created');

        return back()->with('success', 'Product created.');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->safe()->except(['variants']);
        $variants = $request->input('variants', []);

        $product->update($data);

        $syncIds = [];
        foreach ($variants as $variant) {
            if (($variant['_deleted'] ?? false) && ! empty($variant['id'])) {
                $product->variants()->whereKey($variant['id'])->delete();

                continue;
            }

            if (! empty($variant['id'])) {
                $product->variants()->whereKey($variant['id'])->update($variant);
                $syncIds[] = $variant['id'];
            } else {
                $created = $product->variants()->create($variant);
                $syncIds[] = $created->id;
            }
        }

        $product->variants()->whereNotIn('id', $syncIds)->delete();

        ActivityLogger::log("Updated product \"{$product->name}\"", $product, 'updated');

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $name = $product->name;
        $product->delete();

        ActivityLogger::log("Deleted product \"{$name}\"");

        return back()->with('success', 'Product deleted.');
    }

    /**
     * JSON search/lookup for the POS terminal (barcode / SKU / name).
     */
    public function lookup(Request $request, StockService $stockService)
    {
        $request->validate(['q' => ['required', 'string', 'min:1']]);

        $q = $request->q;
        $branchId = $request->user()->branch_id;

        $products = Product::query()
            ->with([
                'unit:id,name,abbreviation',
                'variants:id,product_id,name,sku,barcode,shop_price',
            ])
            ->active()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhereHas('variants', function ($v) use ($q) {
                        $v->where('sku', 'like', "%{$q}%")
                            ->orWhere('barcode', 'like', "%{$q}%");
                    });
            })
            ->select('id', 'name', 'sku', 'barcode', 'unit_id', 'shop_price')
            ->limit(10)
            ->get()
            ->map(function ($product) use ($branchId, $stockService) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'unit' => $product->unit?->abbreviation,
                    'shop_price' => (float) $product->shop_price,
                    'average_cost' => $stockService->averageUnitCost($branchId, $product->id, null),
                    'stock_qty' => (float) ($product->stock()->where('branch_id', $branchId)->sum('quantity') ?? 0),
                    'variants' => $product->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'sku' => $v->sku,
                        'barcode' => $v->barcode,
                        'price' => (float) $v->price,
                        'average_cost' => $stockService->averageUnitCost($branchId, $product->id, $v->id),
                        'stock_qty' => (float) ($product->stock()->where('branch_id', $branchId)->where('product_variant_id', $v->id)->sum('quantity') ?? 0),
                    ]),
                    'default_variant_id' => $product->variants->count() === 1 ? $product->variants->first()->id : null,
                ];
            });

        return response()->json($products);
    }
}
