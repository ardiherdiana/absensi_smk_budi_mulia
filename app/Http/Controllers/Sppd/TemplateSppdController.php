<?php

namespace App\Http\Controllers\Sppd;

use App\Enums\Sppd\SppdTemplate;
use App\Http\Controllers\Controller;
use App\Models\Sppd\Pengaturan;
use App\Services\Sppd\SppdContoh;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TemplateSppdController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('sppd/pengaturan/template-sppd', [
            'saatIni' => SppdTemplate::saatIni()->value,
            'templates' => array_map(fn (SppdTemplate $template) => [
                'value' => $template->value,
                'label' => $template->label(),
                'deskripsi' => $template->deskripsi(),
            ], SppdTemplate::cases()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['template' => ['required', Rule::enum(SppdTemplate::class)]]);

        Pengaturan::simpan(SppdTemplate::KUNCI_PENGATURAN, $data['template']);

        return back()->with('success', 'Template SPPD diubah menjadi '.SppdTemplate::from($data['template'])->label().'.');
    }

    /**
     * Pratinjau template dengan data contoh (tidak menyentuh data pengajuan).
     */
    public function pratinjau(SppdTemplate $template, SppdContoh $contoh)
    {
        return Pdf::loadView($template->view(), ['pengajuan' => $contoh->pengajuan(), 'pratinjau' => true])
            ->setPaper('a4', $template->orientasi())
            ->stream("pratinjau-sppd-{$template->value}.pdf");
    }
}
