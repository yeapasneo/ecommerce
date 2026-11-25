<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Services\ProductSheet;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;

/** Bulk price / stock updates from a spreadsheet: export → edit → upload → preview → apply. */
class ProductImportController extends Controller
{
    public function __construct(protected ProductSheet $sheet) {}

    public function index()
    {
        return Inertia::render('Admin/Products/Import', [
            'columns' => ProductSheet::COLUMNS,
            'editable' => ProductSheet::EDITABLE,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'preview' => null,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only('category_id', 'brand_id', 'stock');

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            $this->sheet->export($out, $filters);
            fclose($out);
        }, 'products-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Upload: show what would change; nothing is saved yet. */
    public function preview(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx']], ['file.mimes' => 'Upload a .csv or .xlsx file.']);
        try {
            $plan = $this->sheet->plan($this->sheet->read($request->file('file')));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        $token = Str::random(32);
        Cache::put("product-import:{$token}", ['user' => $request->user()->id, 'changes' => $plan['changes']], now()->addHour());

        return Inertia::render('Admin/Products/Import', [
            'columns' => ProductSheet::COLUMNS,
            'editable' => ProductSheet::EDITABLE,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'preview' => [
                'token' => $token,
                'file' => $request->file('file')->getClientOriginalName(),
                'count' => count($plan['changes']),
                'changes' => array_slice($plan['changes'], 0, 300),
                'errors' => array_slice($plan['errors'], 0, 200),
                'errorCount' => count($plan['errors']),
                'unchanged' => $plan['unchanged'],
            ],
        ]);
    }

    public function apply(Request $request)
    {
        $token = $request->validate(['token' => ['required', 'string', 'size:32']])['token'];
        $plan = Cache::pull("product-import:{$token}");
        if (! $plan || $plan['user'] !== $request->user()->id) {
            return redirect()->route('admin.products.import')->with('error', 'This preview has expired. Upload the file again.');
        }
        $n = $this->sheet->apply($plan['changes']);
        Activity::log('updated', null, "Bulk import: updated {$n} product(s)/variant(s)");

        return redirect()->route('admin.products.import')->with('success', "Updated {$n} product(s) and variant(s).");
    }
}
