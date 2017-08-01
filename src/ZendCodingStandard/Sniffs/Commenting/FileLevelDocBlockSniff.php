<?php
/**
 * @see       https://github.com/zendframework/zend-coding-standard for the canonical source repository
 * @copyright https://github.com/zendframework/zend-coding-standard/blob/master/COPYRIGHT.md Copyright
 * @license   https://github.com/zendframework/zend-coding-standard/blob/master/LICENSE.md New BSD License
 */

namespace ZendCodingStandard\Sniffs\Commenting;

use ArrayObject;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use ZendCodingStandard\Utils\LicenseUtils;

/**
 * FileLevelDocBlock Sniff
 *
 * - Checks if a file has a valid file-level docblock
 * - Checks for missing/invalid see tag
 * - Checks for missing/invalid copyright tag
 * - Checks for missing/invalid license tag
 * - Checks order of see, copyright and license tags
 */
class FileLevelDocBlockSniff implements Sniff
{
    /**
     * @var string
     */
    private $repo;

    const IGNORE = [
        T_CLASS,
        T_INTERFACE,
        T_TRAIT,
        T_FUNCTION,
        T_CLOSURE,
        T_PUBLIC,
        T_PRIVATE,
        T_PROTECTED,
        T_FINAL,
        T_STATIC,
        T_ABSTRACT,
        T_CONST,
        T_PROPERTY,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,
    ];

    public function __construct()
    {
        // Get current repo name from composer.json
        $content    = file_get_contents('composer.json');
        $content    = json_decode($content, true);
        $this->repo = $content['name'];
    }

    /**
     * Registers the tokens that this sniff wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [T_OPEN_TAG];
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
        // Skip license and copyright file
        if (in_array(substr($phpcsFile->getFilename(), -10), ['LICENSE.md', 'COPYRIGHT.md'])) {
            return ($phpcsFile->numTokens + 1);
        }

        $tokens       = $phpcsFile->getTokens();
        $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

        // Valid file-level DocBlock style
        if ($tokens[$commentStart]['code'] === T_COMMENT) {
            $phpcsFile->addError(
                'You must use "/**" style comments for a file-level DocBlock',
                $commentStart,
                'WrongStyle'
            );
            $phpcsFile->recordMetric($stackPtr, 'File has file-level DocBlock', 'yes');

            return ($phpcsFile->numTokens + 1);
        }

        // File-level DocBlock exists, part 1
        if ($commentStart === false || $tokens[$commentStart]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $phpcsFile->addError('Missing file-level DocBlock', $stackPtr, 'Missing');
            $phpcsFile->recordMetric($stackPtr, 'File has file-level DocBlock', 'no');

            return ($phpcsFile->numTokens + 1);
        }

        $commentEnd = $tokens[$commentStart]['comment_closer'];
        $nextToken  = $phpcsFile->findNext(T_WHITESPACE, $commentEnd + 1, null, true);

        // File-level DocBlock exists, part 2
        if (in_array($tokens[$nextToken]['code'], self::IGNORE) === true) {
            $phpcsFile->addError('Missing file-level DocBlock', $stackPtr, 'Missing');
            $phpcsFile->recordMetric($stackPtr, 'File has file-level DocBlock', 'no');

            return ($phpcsFile->numTokens + 1);
        }

        // File-level DocBlock does exist
        $phpcsFile->recordMetric($stackPtr, 'File has file-level DocBlock', 'yes');

        // No blank line between the open tag and the file comment.
        if ($tokens[$commentStart]['line'] > ($tokens[$stackPtr]['line'] + 1)) {
            $error = 'There must be no blank lines before the file-level DocBlock';
            $phpcsFile->addError($error, $stackPtr, 'SpacingAfterOpen');
        }

        // Exactly one blank line after the file-level DocBlock
        $next = $phpcsFile->findNext(T_WHITESPACE, ($commentEnd + 1), null, true);
        if ($tokens[$next]['line'] !== ($tokens[$commentEnd]['line'] + 2)) {
            $error = 'There must be exactly one blank line after the file-level DocBlock';
            $fix   = $phpcsFile->addFixableError($error, $commentEnd, 'SpacingAfterComment');
            if ($fix === true) {
                $phpcsFile->fixer->addNewline($commentEnd);
            }
        }

        // Required tags in correct order.
        $required = [
            '@see'       => true,
            '@copyright' => true,
            '@license'   => true,
        ];

        $foundTags = new ArrayObject([]);
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            $this->parseCommentTag($tag, $foundTags, $tokens, $required, $commentEnd, $phpcsFile);
        }
        $foundTags = $foundTags->getArrayCopy();

        // If a @link tag was detected, it already triggered errors at this
        // point. Treat @link as @see to suppress even more errors and warnings
        // which should have been fixed by renaming the tag.
        if ($foundTags[0] === '@link') {
            $foundTags[0] = '@see';
        }

        // Check if the tags are in the correct position.
        $pos = 0;
        foreach ($required as $tag => $true) {
            if (in_array($tag, $foundTags) === false) {
                $error = 'Missing %s tag in file-level DocBlock';
                $data  = [$tag];
                $phpcsFile->addError($error, $commentEnd, 'Missing' . ucfirst(substr($tag, 1)) . 'Tag', $data);
            }

            if (isset($foundTags[$pos]) === false) {
                break;
            }

            if ($foundTags[$pos] !== $tag) {
                $error = 'The file-level DocBlock tag in position %s should be the %s tag';
                $data  = [
                    ($pos + 1),
                    $tag,
                ];
                $phpcsFile->addWarning(
                    $error,
                    $tokens[$commentStart]['comment_tags'][$pos],
                    ucfirst(substr($tag, 1)) . 'TagOrder',
                    $data
                );
            }

            $pos++;
        }

        // Ignore the rest of the file.
        return ($phpcsFile->numTokens + 1);
    }

    /**
     * @param string $tag
     * @param ArrayObject $foundTags
     * @param array $tokens
     * @param array $required
     * @param string $commentEnd
     * @param File $phpcsFile
     * @return void
     */
    private function parseCommentTag(
        $tag,
        ArrayObject $foundTags,
        array $tokens,
        array $required,
        $commentEnd,
        File $phpcsFile
    ) {
        $name       = $tokens[$tag]['content'];
        $isRequired = isset($required[$name]);

        $this->sniffForDuplicateRequiredTag($name, $tag, $isRequired, $foundTags, $phpcsFile);
        $foundTags->append($name);
        $this->sniffForLinkTag($name, $tag, $phpcsFile);

        if ($isRequired === false && $name !== '@link') {
            return;
        }

        $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
        if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
            return $this->reportMissingTagContent($name, $tag, $phpcsFile);
        }
        if ($name === '@see' || $name === '@link') {
            return $this->sniffForIncorrectSourceLink($name, $tag, $string, $tokens, $phpcsFile);
        }
        if ($name === '@copyright') {
            return $this->sniffForIncorrectCopyrightLink($tag, $string, $tokens, $phpcsFile);
        }
        if ($name === '@license') {
            return $this->sniffForIncorrectLicenseLink($tag, $string, $tokens, $phpcsFile);
        }
    }

    /**
     * @param string $name
     * @param string $tag
     * @param bool $isRequired
     * @param ArrayObject $foundTags
     * @param File $phpcsFile
     * @return void
     */
    private function sniffForDuplicateRequiredTag($name, $tag, $isRequired, ArrayObject $foundTags, File $phpcsFile)
    {
        if ($isRequired !== true || in_array($name, $foundTags->getArrayCopy()) !== true) {
            return;
        }

        $error = 'Only one %s tag is allowed in a file-level DocBlock';
        $data  = [$name];
        $phpcsFile->addError($error, $tag, 'Duplicate' . ucfirst(substr($name, 1)) . 'Tag', $data);
    }

    /**
     * @param string $name
     * @param string $tag
     * @param File $phpcsFile
     * @return void
     */
    private function sniffForLinkTag($name, $tag, File $phpcsFile)
    {
        if ($name !== '@link') {
            return;
        }

        $error = 'Deprecated @link tag is used, use @see tag instead';
        if (true === $phpcsFile->addFixableError($error, $tag, 'DeprecatedLinkTag')) {
            $phpcsFile->fixer->replaceToken($tag, '@see ');
        }
    }

    /**
     * @param string $name
     * @param string $tag
     * @param File $phpcsFile
     * @return void
     */
    private function reportMissingTagContent($name, $tag, File $phpcsFile)
    {
        $error = 'Content missing for %s tag in file-level DocBlock';
        $data  = [$name];
        $phpcsFile->addError($error, $tag, 'Empty' . ucfirst(substr($name, 1)) . 'Tag', $data);
    }

    /**
     * @param string $name
     * @param string $tag
     * @param string $content
     * @param array $tokens
     * @param File $phpcsFile
     * @return void
     */
    private function sniffForIncorrectSourceLink($name, $tag, $content, array $tokens, File $phpcsFile)
    {
        $expected = sprintf('https://github.com/%s for the canonical source repository', $this->repo);
        if (0 !== preg_match('|^' . $expected . '$|', $tokens[$content]['content'])) {
            return;
        }

        $error = 'Expected "%s" for %s tag';
        if (true === $phpcsFile->addFixableError($error, $tag, 'IncorrectSourceLink', [$expected, $name])) {
            $phpcsFile->fixer->replaceToken($content, $expected);
        }
    }

    /**
     * @param string $tag
     * @param string $content
     * @param array $tokens
     * @param File $phpcsFile
     * @return void
     */
    private function sniffForIncorrectCopyrightLink($tag, $content, array $tokens, File $phpcsFile)
    {
        // Grab copyright date range
        list($firstYear, $lastYear) = LicenseUtils::detectDateRange($tokens[$content]['content']);

        $expected = sprintf('https://github.com/%s/blob/master/COPYRIGHT.md Copyright', $this->repo);
        if (0 !== preg_match('|^' . $expected . '$|', $tokens[$content]['content'])) {
            return;
        }

        $error = 'Expected "%s" for @copyright tag';
        if (true !== $phpcsFile->addFixableError($error, $tag, 'IncorrectCopyrightLink', [$expected])) {
            return;
        }

        $phpcsFile->fixer->replaceToken($content, $expected);
        if ($firstYear !== null) {
            LicenseUtils::buildFiles($firstYear, $lastYear);
        }
    }

    /**
     * @param string $tag
     * @param string $content
     * @param array $tokens
     * @param File $phpcsFile
     * @return void
     */
    private function sniffForIncorrectLicenseLink($tag, $content, array $tokens, File $phpcsFile)
    {
        $expected = sprintf('https://github.com/%s/blob/master/LICENSE.md New BSD License', $this->repo);
        if (0 !== preg_match('|^' . $expected . '$|', $tokens[$content]['content'])) {
            return;
        }

        $error = 'Expected "%s" for @license tag';
        if (true === $phpcsFile->addFixableError($error, $tag, 'IncorrectLicenseLink', [$expected])) {
            $phpcsFile->fixer->replaceToken($content, $expected);
        }
    }
}
