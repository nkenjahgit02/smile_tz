<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/receipt', [
            'paper_width' => (int) Setting::number('receipt.paper_width', 80),
            'header_lines' => (string) Setting::get('receipt.header_lines', ''),
            'footer_message' => (string) Setting::get('receipt.footer_message', 'Thank you for your purchase!'),
            'auto_print' => Setting::bool('receipt.auto_print', true),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'paper_width' => ['required', 'integer', 'in:58,76,80'],
            'header_lines' => ['nullable', 'string', 'max:1000'],
            'footer_message' => ['nullable', 'string', 'max:1000'],
            'auto_print' => ['nullable', 'boolean'],
        ]);

        Setting::set('receipt.paper_width', (string) $validated['paper_width'], 'pos');
        Setting::set('receipt.header_lines', $validated['header_lines'] ?? '', 'pos');
        Setting::set('receipt.footer_message', $validated['footer_message'] ?? '', 'pos');
        Setting::set('receipt.auto_print', filter_var($validated['auto_print'] ?? false, FILTER_VALIDATE_BOOL), 'pos');

        return back()->with('success', 'Receipt settings saved.');
    }
}
