<?php
/**
 * @see       https://github.com/zendframework/zend-coding-standard for the canonical source repository
 * @copyright https://github.com/zendframework/zend-coding-standard/blob/master/COPYRIGHT.md Copyright
 * @license   https://github.com/zendframework/zend-coding-standard/blob/master/LICENSE.md New BSD License
 */

namespace ZendCodingStandard\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SplFileInfo;
use ZendCodingStandard\Utils\LicenseUtils;

/**
 * COPYRIGHT.md Sniff
 *
 * - Checks and creates COPYRIGHT.md in the project root dir
 * - Checks and fixes copyright in COPYRIGHT.md; it should be the current year
 */
class CopyrightSniff implements Sniff
{
    /**
     * @var SplFileInfo
     */
    private $copyrightFile;

    public function __construct()
    {
        $this->copyrightFile = LicenseUtils::getCopyrightFile();
    }

    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_INLINE_HTML];
    }

    /**
     * Called when one of the token types that this sniff is listening for is
     * found.
     *
     * @param File $phpcsFile The PHP_CodeSniffer file where the token was found.
     * @param int $stackPtr The position in the PHP_CodeSniffer file's token stack where the token was found.
     *
     * @return int Optionally returns a stack pointer. The sniff will not be called again on the current file until the
     *     returned stack pointer is reached.
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        // Skip all files except the copyright file
        if (substr($phpcsFile->getFilename(), -10) !== 'COPYRIGHT.md') {
            return ($phpcsFile->numTokens + 1);
        }

        if (! $this->copyrightFile->getRealPath()) {
            $error = 'Missing COPYRIGHT.md file in the component root dir';
            $fix   = $phpcsFile->addFixableError($error, $stackPtr, 'MissingLicense');
            if ($fix === true) {
                LicenseUtils::buildFiles();
            }

            // Ignore the rest of the file.
            return ($phpcsFile->numTokens + 1);
        }

        // Get copyright dates
        list($firstYear, $lastYear) = LicenseUtils::detectDateRange(
            file_get_contents($this->copyrightFile->getRealPath())
        );

        // Check copyright year
        if (($lastYear === null && $firstYear !== gmdate('Y'))
            || ($lastYear !== null && $lastYear !== gmdate('Y'))
        ) {
            $error = sprintf(
                'Expected "Copyright (c) %s" in COPYRIGHT.md',
                LicenseUtils::formatDateRange($firstYear, gmdate('Y'))
            );
            $fix   = $phpcsFile->addFixableError($error, $stackPtr, 'InvalidCopyrightDate');
            if ($fix === true) {
                LicenseUtils::buildFiles($firstYear, $lastYear);
            }
        }

        // Ignore the rest of the file.
        return ($phpcsFile->numTokens + 1);
    }
}
