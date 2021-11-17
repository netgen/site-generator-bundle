<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Command;

use InvalidArgumentException;
use function in_array;
use function mb_strtolower;
use function preg_match;
use function sprintf;

class Validators
{
    /**
     * Validates siteaccess name.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateSiteAccessName(?string $siteaccess = null): string
    {
        // We allow empty siteaccess name in order to quit asking for more
        if (empty($siteaccess)) {
            return '';
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $siteaccess)) {
            throw new InvalidArgumentException('Siteaccess name is not valid.');
        }

        self::validateReservedKeywords($siteaccess);

        return $siteaccess;
    }

    /**
     * Validates language code.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateLanguageCode(?string $languageCode = null): string
    {
        // We allow empty languageCode in order to quit asking for more
        if (empty($languageCode)) {
            return '';
        }

        if (!preg_match('/^[a-z][a-z][a-z]-[A-Z][A-Z]$/', $languageCode)) {
            throw new InvalidArgumentException('Language code name is not valid.');
        }

        return $languageCode;
    }

    public static function validateDesignType(?string $designType = null): string
    {
        if (empty($designType)) {
            return 'local';
        }

        if (!in_array($designType, ['remote', 'local'], true)) {
            throw new InvalidArgumentException("Design type is not valid (choose one of: 'local', 'remote').");
        }

        return $designType;
    }

    /**
     * Validates if value is one of PHP reserved keywords.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateReservedKeywords(string $value): void
    {
        if (in_array(mb_strtolower($value), self::getReservedWords(), true)) {
            throw new InvalidArgumentException(sprintf('The value cannot contain PHP reserved words ("%s").', $value));
        }
    }

    /**
     * Returns the list of PHP reserved keywords.
     */
    public static function getReservedWords(): array
    {
        return [
            'abstract',
            'and',
            'array',
            'as',
            'break',
            'case',
            'catch',
            'class',
            'clone',
            'const',
            'continue',
            'declare',
            'default',
            'do',
            'else',
            'elseif',
            'enddeclare',
            'endfor',
            'endforeach',
            'endif',
            'endswitch',
            'endwhile',
            'extends',
            'final',
            'for',
            'foreach',
            'function',
            'global',
            'goto',
            'if',
            'implements',
            'interface',
            'instanceof',
            'namespace',
            'new',
            'or',
            'private',
            'protected',
            'public',
            'static',
            'switch',
            'throw',
            'try',
            'use',
            'var',
            'while',
            'xor',
            '__CLASS__',
            '__DIR__',
            '__FILE__',
            '__LINE__',
            '__FUNCTION__',
            '__METHOD__',
            '__NAMESPACE__',
            'die',
            'echo',
            'empty',
            'exit',
            'eval',
            'include',
            'include_once',
            'isset',
            'list',
            'require',
            'require_once',
            'return',
            'print',
            'unset',
        ];
    }
}
