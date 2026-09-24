<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SignatureController extends Controller
{
    private const MAX_WIDTH = 600;

    private const MAX_HEIGHT = 240;

    public function edit(Request $request): Response
    {
        return Inertia::render('sppd/signature/edit', [
            'signaturePath' => $request->user()->signature_path,
        ]);
    }

    /**
     * Simpan tanda tangan berupa data URL PNG, baik dari kanvas (tulis tangan) maupun dari gambar yang
     * diunggah (dikonversi ke PNG di browser). Hasilnya dinormalkan dan disimpan dengan nama file baru
     * supaya browser tidak menampilkan versi lama dari cache.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'signature' => ['required', 'string', 'max:2000000', 'regex:/^data:image\/png;base64,/'],
        ]);

        $binary = base64_decode(Str::after($data['signature'], 'base64,'), true);

        $user = $request->user();
        $oldPath = $user->signature_path;
        $path = 'signatures/'.$user->id.'-'.Str::random(10).'.png';

        Storage::disk('public')->put($path, $this->normalizeToPng($binary ?: ''));

        $user->update(['signature_path' => $path]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Tanda tangan digital berhasil disimpan.');
    }

    /**
     * @throws ValidationException
     */
    private function normalizeToPng(string $binary): string
    {
        $source = $binary === '' ? false : @imagecreatefromstring($binary);

        if ($source === false) {
            throw ValidationException::withMessages(['signature' => 'Gambar tanda tangan tidak valid.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);

        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 255, 255, 255, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        ob_start();
        imagepng($target);

        return (string) ob_get_clean();
    }
}
