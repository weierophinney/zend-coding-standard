<?php
/**
 * @see       https://github.com/zendframework/zend-coding-standard for the canonical source repository
 * @copyright https://github.com/zendframework/zend-coding-standard/blob/master/COPYRIGHT.md Copyright
 * @license   https://github.com/zendframework/zend-coding-standard/blob/master/LICENSE.md New BSD License
 */

namespace ZendCodingStandard\Utils;

use SplFileInfo;

/**
 * License Utility class
 */
class LicenseUtils
{
    public static $copyrightLine = 'Copyright (c) %s, Zend Technologies USA, Inc.';

    public static $copyright = <<<EOF
Copyright (c) %s, Zend Technologies USA, Inc.

EOF;

    public static $license = <<<EOF
Copyright (c) %s, Zend Technologies USA, Inc.

All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

- Redistributions of source code must retain the above copyright notice,
  this list of conditions and the following disclaimer.

- Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

- Neither the name of Zend Technologies USA, Inc. nor the names of its
  contributors may be used to endorse or promote products derived from this
  software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

EOF;

    /**
     * Get COPYRIGHT.md as an object
     *
     * @return SplFileInfo
     */
    public static function getCopyrightFile()
    {
        return new SplFileInfo('COPYRIGHT.md');
    }

    /**
     * Get COPYRIGHT.md as an object
     *
     * @return SplFileInfo
     */
    public static function getLicenseFile()
    {
        return new SplFileInfo('LICENSE.md');
    }

    /**
     * Detect and match date range
     *
     * It detects the copyright dates in a string and compares those to the
     * given values. It returns the lowest detected first year and highest
     * detected last year.
     *
     * @param string $string
     * @param string|null $firstYear
     * @param string|null $lastYear
     * @return array
     */
    public static function detectDateRange($string, $firstYear = null, $lastYear = null)
    {
        $matches = [];
        preg_match('|(?<start>[\d]{4})(-(?<end>[\d]{4}))?|', $string, $matches);

        $detectedFirstYear = isset($matches['start']) ? $matches['start'] : null;
        $detectedLastYear = isset($matches['end']) ? $matches['end'] : null;

        if ($firstYear === null || $detectedFirstYear < $firstYear) {
            $firstYear = $detectedFirstYear;
        }

        if ($lastYear === null || $detectedLastYear > $lastYear) {
            $lastYear = $detectedLastYear;
        }

        return [$firstYear, $lastYear];
    }

    /**
     * Format date range
     *
     * Returns a formatted date range from a given first and last year. If the
     * last year is not set or the same as the first year it returns a single
     * year. Otherwise it returns `<first_year>-<last_year>`.
     *
     * @param string|null $firstYear
     * @param string|null $lastYear
     * @return string
     */
    public static function formatDateRange($firstYear = null, $lastYear = null)
    {
        $currentYear = gmdate('Y');
        if ($lastYear === null || $lastYear > $currentYear) {
            $lastYear = $currentYear;
        }

        $dateRange = $lastYear;
        if ($firstYear !== null && $firstYear < $lastYear) {
            $dateRange = sprintf('%s-%s', $firstYear, $lastYear);
        }

        return $dateRange;
    }

    /**
     * Build copyright and license files
     *
     * This detects the current date range if any of the two files exist. And
     * updates their content in case of any detected changes.
     *
     * @param null $firstYear
     * @param null $lastYear
     * @param SplFileInfo|null $copyrightFile
     * @param SplFileInfo|null $licenseFile
     */
    public static function buildFiles(
        $firstYear = null,
        $lastYear = null,
        SplFileInfo $copyrightFile = null,
        SplFileInfo $licenseFile = null
    ) {
        if ($copyrightFile === null) {
            $copyrightFile = self::getCopyrightFile();
        }

        if ($licenseFile === null) {
            $licenseFile = self::getLicenseFile();
        }

        // Get copyright dates
        $oldCopyright = null;
        if ($copyrightFile->isReadable()) {
            $oldCopyright = file_get_contents($copyrightFile->getPathname());
            list($firstYear, $lastYear) = self::detectDateRange($oldCopyright, $firstYear, $lastYear);
        }

        // Get license dates
        $oldLicense = null;
        if ($licenseFile->isReadable()) {
            $oldLicense = file_get_contents($licenseFile->getPathname());
            list($firstYear, $lastYear) = self::detectDateRange($oldLicense, $firstYear, $lastYear);
        }

        // Format date range, enforce current year
        $copyrightDateRange = self::formatDateRange($firstYear, gmdate('Y'));

        // Save new copyright content if it's updated
        $newCopyright = sprintf(self::$copyright, $copyrightDateRange);
        if ($oldCopyright === null || $oldCopyright !== $newCopyright) {
            file_put_contents($copyrightFile->getPathname(), $newCopyright);
        }

        // Save new license if it's updated
        $newLicense = sprintf(self::$license, $copyrightDateRange);
        if ($oldLicense === null || $oldLicense !== $newLicense) {
            file_put_contents($licenseFile->getPathname(), $newLicense);
        }
    }
}
