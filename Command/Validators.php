<?php

namespace Netgen\Bundle\GeneratorBundle\Command;

use InvalidArgumentException;

class Validators
{
    public static function validateCamelCaseName( $name )
    {
        if ( !preg_match( '/^[A-Z][a-zA-Z0-9_]*$/', $name ) )
        {
            throw new InvalidArgumentException( 'The name is not valid.' );
        }

        if ( preg_match( '/bundle$/i', $name ) )
        {
            throw new InvalidArgumentException( 'The name cannot end with "Bundle".' );
        }

        self::validateReservedKeywords( $name );

        return $name;
    }

    public static function validateLowerCaseName( $name )
    {
        if ( !preg_match( '/^[a-z][a-z0-9_]*$/', $name ) )
        {
            throw new InvalidArgumentException( 'The name is not valid.' );
        }

        if ( preg_match( '/bundle$/i', $name ) )
        {
            throw new InvalidArgumentException( 'The name cannot end with "Bundle".' );
        }

        self::validateReservedKeywords( $name );

        return $name;
    }

    public static function validateSiteAccessName( $siteaccess )
    {
        // We allow empty siteaccess name in order to quit asking for more
        if ( empty( $siteaccess ) )
        {
            return '';
        }

        if ( $siteaccess === 'administration' )
        {
            throw new InvalidArgumentException( 'Siteaccess name cannot be equal to "administration"' );
        }

        if ( !preg_match( '/^[a-z][a-z0-9_]*$/', $siteaccess ) )
        {
            throw new InvalidArgumentException( 'Siteaccess name is not valid.' );
        }

        self::validateReservedKeywords( $siteaccess );

        return $siteaccess;
    }

    public static function validateLanguageCode( $languageCode )
    {
        // We allow empty languageCode in order to quit asking for more
        if ( empty( $languageCode ) )
        {
            return '';
        }

        if ( !preg_match( '/^[a-z][a-z][a-z]-[A-Z][A-Z]$/', $languageCode ) )
        {
            throw new InvalidArgumentException( 'Language code name is not valid.' );
        }

        return $languageCode;
    }

    public static function validateReservedKeywords( $value )
    {
        $reserved = self::getReservedWords();
        if ( in_array( strtolower( $value ), $reserved ) )
        {
            throw new InvalidArgumentException( sprintf( 'The value cannot contain PHP reserved words ("%s").', $value ) );
        }
    }

    public static function validateNotEmpty( $value )
    {
        if ( empty( $value ) )
        {
            throw new InvalidArgumentException( 'Value cannot be empty.' );
        }

        return $value;
    }

    public static function validateBundleNamespace( $namespace )
    {
        if ( !preg_match( '/Bundle$/', $namespace ) )
        {
            throw new InvalidArgumentException( 'The namespace must end with "Bundle".' );
        }

        $namespace = strtr( $namespace, '/', '\\' );
        if ( !preg_match( '/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+$/', $namespace ) )
        {
            throw new InvalidArgumentException( 'The namespace contains invalid characters.' );
        }

        self::validateReservedKeywords( $namespace );

        // Validate that the namespace is at least one level deep
        if ( strpos( $namespace, '\\' ) === false )
        {
            $msg = array();
            $msg[] = sprintf( 'The namespace must contain a vendor namespace (e.g. "VendorName\%s" instead of simply "%s").', $namespace, $namespace );
            $msg[] = 'If you\'ve specified a vendor namespace, did you forget to surround it with quotes (init:bundle "Acme\BlogBundle")?';

            throw new InvalidArgumentException( implode( "\n\n", $msg ) );
        }

        return $namespace;
    }

    public static function validateBundleName( $bundle )
    {
        if ( !preg_match( '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $bundle ) )
        {
            throw new InvalidArgumentException( 'The bundle name contains invalid characters.' );
        }

        if ( !preg_match( '/Bundle$/', $bundle ) )
        {
            throw new InvalidArgumentException( 'The bundle name must end with Bundle.' );
        }

        return $bundle;
    }

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
