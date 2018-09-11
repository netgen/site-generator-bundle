<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Command;

use InvalidArgumentException;

class Validators
{
    /**
     * Validates camel case name.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateCamelCaseName(string $name): string
    {
        if (!preg_match('/^[A-Z][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('The name is not valid.');
        }

        if (preg_match('/bundle$/i', $name)) {
            throw new InvalidArgumentException('The name cannot end with "Bundle".');
        }

        self::validateReservedKeywords($name);

        return $name;
    }

    /**
     * Validates lower case name.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateLowerCaseName(string $name): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('The name is not valid.');
        }

        if (preg_match('/bundle$/i', $name)) {
            throw new InvalidArgumentException('The name cannot end with "Bundle".');
        }

        self::validateReservedKeywords($name);

        return $name;
    }

    /**
     * Validates siteaccess name.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateSiteAccessName(string $siteaccess = null): string
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
     * Validates siteaccess name.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateAdminSiteAccessName(string $siteaccess): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $siteaccess)) {
            throw new InvalidArgumentException('Admin siteaccess name is not valid.');
        }

        self::validateReservedKeywords($siteaccess);

        return $siteaccess;
    }

    /**
     * Validates language code.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateLanguageCode(string $languageCode = null): string
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

    /**
     * Validates if value is not empty.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateNotEmpty(string $value): string
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Value cannot be empty.');
        }

        return $value;
    }

    /**
     * Validates bundle namespace.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateBundleNamespace(string $namespace): string
    {
        if (!preg_match('/Bundle$/', $namespace)) {
            throw new InvalidArgumentException('The namespace must end with "Bundle".');
        }

        $namespace = str_replace('/', '\\', $namespace);
        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+$/', $namespace)) {
            throw new InvalidArgumentException('The namespace contains invalid characters.');
        }

        self::validateReservedKeywords($namespace);

        $explodedNamespace = explode('\\', $namespace);
        if (count($explodedNamespace) !== 3 || $explodedNamespace[1] !== 'Bundle') {
            throw new InvalidArgumentException('The namespace must be in format <Client>\Bundle\<Project>.');
        }

        return $namespace;
    }

    /**
     * Validates bundle name.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateBundleName(string $bundle): string
    {
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $bundle)) {
            throw new InvalidArgumentException('The bundle name contains invalid characters.');
        }

        if (!preg_match('/Bundle$/', $bundle)) {
            throw new InvalidArgumentException('The bundle name must end with Bundle.');
        }

        return $bundle;
    }

    /**
     * Validates if value is one of PHP reserved keywords.
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateReservedKeywords(string $value): void
    {
        if (in_array(strtolower($value), self::getReservedWords(), true)) {
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
