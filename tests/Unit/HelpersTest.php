<?php

namespace Tests\Unit;

use App\Ai\AiClient;
use App\Ai\ProductWriter;
use App\Services\Returns;
use App\Support\Phone;
use PHPUnit\Framework\TestCase;

/** Pure functions that need no database or app. */
class HelpersTest extends TestCase
{
    public function test_phone_numbers_are_normalised(): void
    {
        $this->assertSame('01712345678', Phone::normalize('+880 1712-345678'));
        $this->assertSame('01712345678', Phone::normalize('8801712345678'));
        $this->assertTrue(Phone::isValid('01712345678'));
        $this->assertFalse(Phone::isValid('12345'));
        $this->assertSame(Phone::key('+8801712345678'), Phone::key('01712345678'));
    }

    public function test_serial_numbers_are_cleaned(): void
    {
        $this->assertSame('AB-123', Returns::cleanSerial(' ab-12 3 '));
        $this->assertNull(Returns::cleanSerial('   '));
        $this->assertNull(Returns::cleanSerial(null));
    }

    public function test_ai_html_is_reduced_to_simple_tags(): void
    {
        $this->assertSame('<p>Hi <strong>there</strong></p>alert(1)', ProductWriter::sanitizeHtml('<p class="x" onclick="y()">Hi <strong>there</strong></p><script>alert(1)</script>'));
    }

    public function test_ai_json_is_read_from_fenced_or_wrapped_text(): void
    {
        $this->assertSame(['a' => 1], AiClient::decodeJson("```json\n{\"a\": 1}\n```"));
        $this->assertSame(['a' => 1], AiClient::decodeJson('Here you go: {"a": 1} Thanks!'));
        $this->assertNull(AiClient::decodeJson('no json here'));
    }
}
