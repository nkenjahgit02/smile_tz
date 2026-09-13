<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('catalog/index', [
            'categories' => Category::with('parent:id,name')->orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ]);

        $category = Category::create($validated);

        ActivityLogger::log("Created category \"{$category->name}\"", $category, 'created');

        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id', 'not_in:'.$category->id],
            'is_active' => ['boolean'],
        ]);

        $category->update($validated);

        ActivityLogger::log("Updated category \"{$category->name}\"", $category, 'updated');

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(Category $category)
    {
        $name = $category->name;
        $category->delete();

        ActivityLogger::log("Deleted category \"{$name}\"");

        return back()->with('success', 'Category deleted.');
    }

    public function storeBrand(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')],
        ]);

        $brand = Brand::create($validated);

        ActivityLogger::log("Created brand \"{$brand->name}\"", $brand, 'created');

        return back()->with('success', 'Brand created.');
    }

    public function updateBrand(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brand->id)],
            'is_active' => ['boolean'],
        ]);

        $brand->update($validated);

        ActivityLogger::log("Updated brand \"{$brand->name}\"", $brand, 'updated');

        return back()->with('success', 'Brand updated.');
    }

    public function destroyBrand(Brand $brand)
    {
        $name = $brand->name;
        $brand->delete();

        ActivityLogger::log("Deleted brand \"{$name}\"");

        return back()->with('success', 'Brand deleted.');
    }

    public function storeUnit(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')],
            'abbreviation' => ['required', 'string', 'max:20', Rule::unique('units', 'abbreviation')],
        ]);

        $unit = Unit::create($validated);

        ActivityLogger::log("Created unit \"{$unit->name}\"", $unit, 'created');

        return back()->with('success', 'Unit created.');
    }

    public function updateUnit(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')->ignore($unit->id)],
            'abbreviation' => ['required', 'string', 'max:20', Rule::unique('units', 'abbreviation')->ignore($unit->id)],
            'is_active' => ['boolean'],
        ]);

        $unit->update($validated);

        ActivityLogger::log("Updated unit \"{$unit->name}\"", $unit, 'updated');

        return back()->with('success', 'Unit updated.');
    }

    public function destroyUnit(Unit $unit)
    {
        $name = $unit->name;
        $unit->delete();

        ActivityLogger::log("Deleted unit \"{$name}\"");

        return back()->with('success', 'Unit deleted.');
    }
}
