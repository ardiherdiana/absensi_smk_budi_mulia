<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureTest extends TestCase
{
    private User $kepsek;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $this->kepsek = User::factory()->create()->assignRole(RoleName::KepalaSekolah->value);
    }

    private function pngDataUrl(int $width = 40, int $height = 20): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 30, 120));

        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    public function test_signature_page_can_be_opened_by_kepala_sekolah(): void
    {
        $this->actingAs($this->kepsek)->get(route('sppd.signature.edit'))->assertOk();
    }

    public function test_signature_can_be_saved_and_updated_with_a_new_file_each_time(): void
    {
        $this->actingAs($this->kepsek)
            ->post(route('sppd.signature.update'), ['signature' => $this->pngDataUrl()])
            ->assertSessionHasNoErrors();

        $first = $this->kepsek->fresh()->signature_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($this->kepsek)
            ->post(route('sppd.signature.update'), ['signature' => $this->pngDataUrl(80, 30)])
            ->assertSessionHasNoErrors();

        $second = $this->kepsek->fresh()->signature_path;
        $this->assertNotSame($first, $second, 'a new path is needed so browsers do not show a cached image');
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_large_signature_is_scaled_down(): void
    {
        $this->actingAs($this->kepsek)->post(route('sppd.signature.update'), ['signature' => $this->pngDataUrl(1800, 600)]);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($this->kepsek->fresh()->signature_path));

        $this->assertLessThanOrEqual(600, $width);
        $this->assertLessThanOrEqual(240, $height);
    }

    public function test_invalid_signature_is_rejected_and_keeps_the_previous_one(): void
    {
        $this->actingAs($this->kepsek)->post(route('sppd.signature.update'), ['signature' => $this->pngDataUrl()]);
        $saved = $this->kepsek->fresh()->signature_path;

        $this->actingAs($this->kepsek)
            ->post(route('sppd.signature.update'), ['signature' => 'data:image/png;base64,'.base64_encode('bukan-gambar')])
            ->assertSessionHasErrors('signature');

        $this->actingAs($this->kepsek)
            ->post(route('sppd.signature.update'), ['signature' => 'data:image/jpeg;base64,'.base64_encode('x')])
            ->assertSessionHasErrors('signature');

        $this->assertSame($saved, $this->kepsek->fresh()->signature_path);
        Storage::disk('public')->assertExists($saved);
    }
}
