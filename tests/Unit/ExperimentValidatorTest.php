<?php

namespace Piwik\Plugins\SimpleABTesting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\SimpleABTesting\Validation\ExperimentValidator;

/**
 * The Tag Manager tag's parameter value is a comma-joined string
 * ("name,from,to,css,js" — see Template/Tag/SimpleABTestingTag.php) that is
 * never URL-encoded on the name. A comma in the experiment name silently
 * shifts every field after it, corrupting the dates, CSS and JS the tag
 * reads at runtime — not a validation nicety, a silent-corruption bug.
 */
final class ExperimentValidatorTest extends TestCase
{
    public function test_name_with_comma_is_rejected(): void
    {
        $error = ExperimentValidator::validateName('spring sale, v2');
        $this->assertNotNull($error);
        $this->assertStringContainsString('comma', $error);
    }

    public function test_empty_name_is_rejected(): void
    {
        $error = ExperimentValidator::validateName('');
        $this->assertNotNull($error);
    }

    public function test_blank_name_is_rejected(): void
    {
        $error = ExperimentValidator::validateName('   ');
        $this->assertNotNull($error);
    }

    public function test_ordinary_name_is_accepted(): void
    {
        $this->assertNull(ExperimentValidator::validateName('spring-sale-v2'));
    }
}
