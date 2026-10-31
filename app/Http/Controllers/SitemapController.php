<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(30), function () {
            $urls = [
                ['loc' => url('/'), 'changefreq' => 'daily', 'priority' => '1.0'],
                ['loc' => route('home'), 'changefreq' => 'hourly', 'priority' => '0.9'],
                ['loc' => route('categories.index'), 'changefreq' => 'weekly', 'priority' => '0.6'],
            ];

            foreach (Category::query()->active()->get(['slug', 'updated_at']) as $c) {
                $urls[] = ['loc' => route('categories.show', $c), 'lastmod' => $c->updated_at?->toAtomString(), 'changefreq' => 'daily', 'priority' => '0.7'];
            }
            foreach (Store::query()->active()->latest('updated_at')->limit(5000)->get(['slug', 'updated_at']) as $s) {
                $urls[] = ['loc' => route('stores.show', $s), 'lastmod' => $s->updated_at?->toAtomString(), 'changefreq' => 'daily', 'priority' => '0.7'];
            }
            foreach (Product::query()->published()->latest('products.updated_at')->limit(40000)->get(['products.slug', 'products.updated_at']) as $p) {
                $urls[] = ['loc' => route('products.show', $p), 'lastmod' => $p->updated_at?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.8'];
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as $u) {
                $out .= '  <url><loc>'.e($u['loc']).'</loc>'
                    .(! empty($u['lastmod']) ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '')
                    .'<changefreq>'.$u['changefreq'].'</changefreq><priority>'.$u['priority'].'</priority></url>'."\n";
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /sell', 'Disallow: /admin', 'Disallow: /inbox', 'Disallow: /profile', 'Disallow: /search?', 'Allow: /', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
