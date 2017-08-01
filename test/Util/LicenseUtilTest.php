<?php
/**
 * @see       https://github.com/zendframework/zend-coding-standard for the canonical source repository
 * @copyright https://github.com/zendframework/zend-coding-standard/blob/master/COPYRIGHT.md Copyright
 * @license   https://github.com/zendframework/zend-coding-standard/blob/master/LICENSE.md New BSD License
 */

namespace ZendCodingStandardTest\Util;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use ZendCodingStandard\Utils\LicenseUtils;

class LicenseUtilTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    private $root;

    public function setUp()
    {
        $this->root = vfsStream::setup('tmp');
    }

    /**
     * @dataProvider dateRangeDetectionProvider
     */
    public function testDateRangeDetection($string, $firstYear, $lastYear, $expectedFirstYear, $expectedLastYear)
    {
        list($actualFirstYear, $actualLastYear) = LicenseUtils::detectDateRange($string, $firstYear, $lastYear);

        $this->assertEquals($expectedFirstYear, $actualFirstYear);
        $this->assertEquals($expectedLastYear, $actualLastYear);
    }

    public function dateRangeDetectionProvider()
    {
        return [
            'empty'          => ['Copyright (c) Foo', null, null, null, null],
            '2014'           => ['(c) 2014 Foo', null, null, 2014, null],
            '2015-2016'      => ['(c) 2015-2016 Bar', null, null, 2015, 2016],
            '2016-current'   => [sprintf('(c) 2016-%s', gmdate('Y')), null, null, 2016, gmdate('Y')],
            'current'        => [sprintf('(c) %s', gmdate('Y')), null, null, gmdate('Y'), null],
            'o2012'          => ['(c) 2014 Foo', 2012, null, 2012, null],
            'o2016'          => ['(c) 2014 Foo', 2016, null, 2014, null],
            'o2012-o2015'    => ['(c) 2014 Foo', 2012, 2015, 2012, 2015],
            'o2012-o2016'    => ['(c) 2014-2015', 2012, 2016, 2012, 2016],
            'o2016-oCurrent' => [sprintf('(c) 2016-%s', gmdate('Y')), 2012, 2016, 2012, gmdate('Y')],
            'oCurrent'       => [sprintf('(c) %s', gmdate('Y')), 2012, 2014, 2012, 2014],
        ];
    }

    /**
     * @dataProvider dateRangeFormatProvider
     */
    public function testFormatDateRange($firstYear, $lastYear, $expected)
    {
        $this->assertEquals($expected, LicenseUtils::formatDateRange($firstYear, $lastYear));
    }

    public function dateRangeFormatProvider()
    {
        return [
            '2014'         => ['2014', null, '2014-' . gmdate('Y')],
            '2015-2016'    => ['2015', '2016', '2015-2016'],
            '2016-current' => ['2016', gmdate('Y'), '2016-' . gmdate('Y')],
            'current'      => [gmdate('Y'), null, gmdate('Y')],
        ];
    }

    public function testBuildNewFiles()
    {
        $firstYear = null;
        $lastYear = null;
        $copyrightFile = new SplFileInfo($this->root->url() . '/COPYRIGHT.tmp');
        $licenseFile = new SplFileInfo($this->root->url() . '/LICENSE.tmp');

        LicenseUtils::buildFiles($firstYear, $lastYear, $copyrightFile, $licenseFile);

        $this->assertTrue($this->root->hasChild('COPYRIGHT.tmp'));
        $this->assertEquals(
            sprintf(LicenseUtils::$copyright, LicenseUtils::formatDateRange(gmdate('Y'))),
            $this->root->getChild('COPYRIGHT.tmp')->getContent()
        );

        $this->assertTrue($this->root->hasChild('LICENSE.tmp'));
        $this->assertEquals(
            sprintf(LicenseUtils::$license, LicenseUtils::formatDateRange(gmdate('Y'))),
            $this->root->getChild('LICENSE.tmp')->getContent()
        );
    }

    public function testUpdateBothFilesWithSameDates()
    {
        $copyrightFile = new SplFileInfo($this->root->url() . '/COPYRIGHT.tmp');
        $licenseFile = new SplFileInfo($this->root->url() . '/LICENSE.tmp');

        file_put_contents(
            $copyrightFile->getPathname(),
            sprintf(LicenseUtils::$copyright, LicenseUtils::formatDateRange('2015'))
        );

        file_put_contents(
            $licenseFile->getPathname(),
            sprintf(LicenseUtils::$license, LicenseUtils::formatDateRange('2016-2017'))
        );

        LicenseUtils::buildFiles('2016', '2016', $copyrightFile, $licenseFile);

        $this->assertTrue($this->root->hasChild('COPYRIGHT.tmp'));
        $this->assertEquals(
            sprintf(LicenseUtils::$copyright, LicenseUtils::formatDateRange('2015', gmdate('Y'))),
            $this->root->getChild('COPYRIGHT.tmp')->getContent()
        );

        $this->assertTrue($this->root->hasChild('LICENSE.tmp'));
        $this->assertEquals(
            sprintf(LicenseUtils::$license, LicenseUtils::formatDateRange('2015', gmdate('Y'))),
            $this->root->getChild('LICENSE.tmp')->getContent()
        );
    }
}
