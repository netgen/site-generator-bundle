<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command;

use InvalidArgumentException;

class Validators
{
    /**
     * Validates camel case name.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException If validation did not pass
     *
     * @return string
     */
    public static function validateCamelCaseName($name)
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
     * @param string $name
     *
     * @throws \InvalidArgumentException If validation did not pass
     *
     * @return string
     */
    public static function validateLowerCaseName($name)
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
     * @param string $siteaccess
     *
     * @throws \InvalidArgumentException If validation did not pass
     *
     * @return string
     */
    public static function validateSiteAccessName($siteaccess)
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
     * @param string $siteaccess
     *
     * @throws \InvalidArgumentException If validation did not pass
     *
     * @return string
     */
    public static function validateAdminSiteAccessName($siteaccess)
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
     * @param string $languageCode
     *
     * @throws \InvalidArgumentException If validation did not pass
     *
     * @return string
     */
    public static function validateLanguageCode($languageCode)
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
     * @param string $value
     *
     * @throws \InvalidArgumentException If validation did not pass
     *
     * @return string
     */
    public static function validateNotEmpty($value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Value cannot be empty.');
        }

        return $value;
    }

    /**
     * Validates bundle namespace.
     *
     * @param string $namespace
     *
     * @throws \InvalidArgumentException If validation did not pass
     *
     * @return string
     */
    public static function validateBundleNamespace($namespace)
    {
        if (!preg_match('/Bundle$/', $namespace)) {
            throw new InvalidArgumentException('The namespace must end with "Bundle".');
        }

        $namespace = strtr($namespace, '/', '\\');
        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+$/', $namespace)) {
            throw new InvalidArgumentException('The namespace contains invalid characters.');
        }

        self::validateReservedKeywords($namespace);

        $explodedNamespace = explode('\\', $namespace);
        if (count($explodedNamespace) != 3 || $explodedNamespace[1] !== 'Bundle') {
            throw new InvalidArgumentException('The namespace must be in format <Client>\Bundle\<Project>.');
        }

        return $namespace;
    }

    /**
     * Validates bundle name.
     *
     * @param string $bundle
     *
     * @throws \InvalidArgumentException If validation did not pass
     *
     * @return string
     */
    public static function validateBundleName($bundle)
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
     * Validates if value is one of PHP's reserved keywords.
     *
     * @param string $value
     *
     * @throws \InvalidArgumentException If validation did not pass
     */
    public static function validateReservedKeywords($value)
    {
        $reserved = self::getReservedWords();
        if (in_array(strtolower($value), $reserved)) {
            throw new InvalidArgumentException(sprintf('The value cannot contain PHP reserved words ("%s").', $value));
        }
    }

    /**
     * Returns the list of PHP's reserved keywords.
     *
     * @return array
     */
    public static function getReservedWords()
    {
        return array(
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
        );
    }
}
