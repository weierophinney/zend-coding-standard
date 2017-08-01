<?php
/**
 * @see       https://github.com/zendframework/zend-coding-standard for the canonical source repository
 * @copyright https://github.com/zendframework/zend-coding-standard/blob/master/COPYRIGHT.md Copyright
 * @license   https://github.com/zendframework/zend-coding-standard/blob/master/LICENSE.md New BSD License
 */

namespace ZendCodingStandardTest;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Util\Tokens;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\InvalidArgumentHelper;

class SniffTestCase extends TestCase
{
    /**
     * @var Config
     */
    protected $config;

    protected function setUp()
    {
        parent::setUp();

        // Init PHP_CodeSniffer
        if (defined('PHP_CODESNIFFER_IN_TESTS') === false) {
            define('PHP_CODESNIFFER_IN_TESTS', false);
        }
        if (defined('PHP_CODESNIFFER_CBF') === false) {
            define('PHP_CODESNIFFER_CBF', false);
        }
        if (defined('PHP_CODESNIFFER_VERBOSITY') === false) {
            define('PHP_CODESNIFFER_VERBOSITY', 0);
        }

        // Define tokens
        new Tokens();

        // Setup config
        $this->config = new Config();

        // Don't cache files
        $this->config->cache       = false;

        // Set ruleset
        $this->config->standards   = ['src/ZendCodingStandard/ruleset.xml'];
    }

    public function processAsset($asset)
    {
        $ruleset = new Ruleset($this->config);

        $file = new LocalFile($asset, $ruleset, $this->config);
        $file->process();

        return $file;
    }

    public function assertErrorCount($expectedCount, File $file)
    {
        if (! is_int($expectedCount)) {
            throw InvalidArgumentHelper::factory(1, 'integer');
        }

        $message = sprintf(
            'Failed asserting that "%s" has %d violations.',
            str_replace(__DIR__, 'test', $file->getFilename()),
            $expectedCount
        );
        $this->assertEquals($expectedCount, $file->getErrorCount(), $message);
    }

    public function assertHasError($expectedError, File $file)
    {
        foreach ($file->getErrors() as $line => $lineErrors) {
            foreach ($lineErrors as $column => $errors) {
                foreach ($errors as $error) {
                    if (isset($error['source']) && $error['source'] === $expectedError) {
                        $this->assertTrue(true);

                        return;
                    }
                }
            }
        }

        $message = sprintf(
            'Failed asserting that "%s" has "%s" error.',
            str_replace(__DIR__, 'test', $file->getFilename()),
            $expectedError
        );
        $this->assertTrue(false, $message);
    }

    public function assertWarningCount($expectedCount, File $file)
    {
        if (! is_int($expectedCount)) {
            throw InvalidArgumentHelper::factory(1, 'integer');
        }

        $message = sprintf(
            'Failed asserting that "%s" has %d warnings.',
            str_replace(__DIR__, 'test', $file->getFilename()),
            $expectedCount
        );
        $this->assertEquals($expectedCount, $file->getWarningCount(), $message);
    }

    public function assertHasWarning($expectedError, File $file)
    {
        foreach ($file->getWarnings() as $line => $lineErrors) {
            foreach ($lineErrors as $column => $errors) {
                foreach ($errors as $error) {
                    if (isset($error['source']) && $error['source'] === $expectedError) {
                        $this->assertTrue(true);

                        return;
                    }
                }
            }
        }

        $message = sprintf(
            'Failed asserting that "%s" has "%s" warning.',
            str_replace(__DIR__, 'test', $file->getFilename()),
            $expectedError
        );
        $this->assertTrue(false, $message);
    }

    public function assertAssetCanBeFixed($fixed, File $file)
    {
        if ($fixed === null) {
            $message = sprintf(
                'Failed asserting that "%s" has no fixable violations.',
                str_replace(__DIR__, 'test', $file->getFilename())
            );
            $this->assertEquals(0, $file->getFixableCount(), $message);

            return;
        }

        // Try to fix the file
        $file->fixer->fixFile();
        $message = sprintf(
            'Failed to fix %d fixable violations in "%s".',
            $file->getFixableCount(),
            str_replace(__DIR__, 'test', $file->getFilename())
        );
        $this->assertEquals(0, $file->getFixableCount(), $message);

        // Validate fixes
        $message = sprintf(
            'Failed asserting that "%s" has all fixable violations fixed.',
            str_replace(__DIR__, 'test', $file->getFilename())
        );
        $this->assertEquals('', trim($file->fixer->generateDiff($fixed)), $message);
    }
}
