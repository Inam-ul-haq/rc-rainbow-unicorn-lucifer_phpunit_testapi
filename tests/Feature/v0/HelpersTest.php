<?php

namespace Tests\Feature\v0;

use Tests\TestCase;
use App\Helpers\Helper;
use Tests\Feature\V0Test;
use App\Traits\PHPUnitSetup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HelpersTest extends V0Test
{
    use PHPUnitSetup;

    public function testCanGetDefaultBarcode()
    {
        $barcode = Helper::generateBarcodeNumber();
        $this->assertStringStartsWith('501', $barcode);
        $this->assertEquals(13, strlen($barcode));
    }

    public function testCanGetLongBarcode()
    {
        $barcode = Helper::generateBarcodeNumber(21);
        $this->assertStringStartsWith('501', $barcode);
        $this->assertEquals(21, strlen($barcode));
    }

    public function testCanGetCustomPrefixBarcode()
    {
        $barcode = Helper::generateBarcodeNumber(13, '514');
        $this->assertStringStartsWith('514', $barcode);
        $this->assertEquals(13, strlen($barcode));
    }

    public function testCanValidateOddLengthBarcode()
    {
        $barcode = Helper::generateBarcodeNumber(13);
        $this->assertTrue(Helper::validateBarcode($barcode));
    }

    public function testCanValidateEvenLengthBarcode()
    {
        $barcode = Helper::generateBarcodeNumber(14);
        $this->assertTrue(Helper::validateBarcode($barcode));
    }

    public function testCanValidateBadBarcode()
    {
        $barcode = '1949952059103';
        $this->assertFalse(Helper::validateBarcode($barcode));
    }

    public function testCanCreateBarcodeImage()
    {
        $barcode_number = '0123456789012';
        Storage::fake('public_web_assets');

        Helper::write1DBarcodePngImage($barcode_number, 'barcodes/barcode.png');

        Storage::disk('public_web_assets')->assertExists('/barcodes/'.'barcode.png');
    }

    public function testCantCreateBarcodeImageWithInvalidNumber()
    {
        $barcode_number = '0123456789013';
        Storage::fake('public_web_assets');

        $this->expectException(\Exception::class);
        Helper::write1DBarcodePngImage($barcode_number, 'barcode.png');
    }

    public function testDateMatchesFormat()
    {
        $this->assertTrue(Helper::dateMatchesFormat('2020-06-09', 'Y-m-d'));
        $this->assertFalse(Helper::dateMatchesFormat('2020-06-9 13:32:57', 'Y-m-d'));
        $this->assertFalse(Helper::dateMatchesFormat('06-01', 'Y-m-d'));
        $this->assertFalse(Helper::dateMatchesFormat('20-06-01', 'Y-m-d'));
        $this->assertFalse(Helper::dateMatchesFormat('2020-13-01', 'Y-m-d'));
        $this->assertFalse(Helper::dateMatchesFormat('2020-06-45', 'Y-m-d'));
        $this->assertFalse(Helper::dateMatchesFormat('bad-input', 'Y-m-d'));
    }
}
