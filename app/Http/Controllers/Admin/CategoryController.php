<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $seo = Seo::make('Categories · Admin', index: false);

        return Inertia::render('Admin/Categories', [
            'list' => Category::query()->withCount('products')->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Category $c) => $c->only(['id', 'name', 'slug', 'icon', 'accent', 'sort', 'is_active']) + ['products_count' => $c->products_count]),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $request->merge(['slug' => $data['slug']])->validate(['slug' => [Rule::unique('categories', 'slug')]]);
        $category = Category::create($data);
        AuditLog::record('category.created', $category);

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $data['slug'] = $data['slug'] ?: $category->slug;
        $category->update($data);
        AuditLog::record('category.updated', $category);

        return back()->with('success', 'Category saved.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['nullable', 'string', 'max:96', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'icon' => ['nullable', 'string', 'max:40'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]) + ['sort' => 0];
    }
}
