<?php

namespace Netgen\Bundle\GeneratorBundle\Command;

class Validators
{
    public static function validateBundleNamespace($namespace)
    {
        if (!preg_match('/Bundle$/', $namespace)) {
            throw new \InvalidArgumentException('The namespace must end with Bundle.');
        }

        $namespace = strtr($namespace, '/', '\\');
        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+$/', $namespace)) {
            throw new \InvalidArgumentException('The namespace contains invalid characters.');
        }

        // validate reserved keywords
        $reserved = self::getReservedWords();
        foreach (explode('\\', $namespace) as $word) {
            if (in_array(strtolower($word), $reserved)) {
                throw new \InvalidArgumentException(sprintf('The namespace cannot contain PHP reserved words ("%s").', $word));
            }
        }

        // validate that the namespace is at least one level deep
        if (false === strpos($namespace, '\\')) {
            $msg = array();
            $msg[] = sprintf('The namespace must contain a vendor namespace (e.g. "VendorName\%s" instead of simply "%s").', $namespace, $namespace);
            $msg[] = 'If you\'ve specified a vendor namespace, did you forget to surround it with quotes (init:bundle "Client\Bundle\ProjectBundle")?';

            throw new \InvalidArgumentException(implode("\n\n", $msg));
        }

        return $namespace;
    }

    public static function validateBundleName($bundle)
    {
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $bundle)) {
            throw new \InvalidArgumentException('The bundle name contains invalid characters.');
        }

        if (!preg_match('/Bundle$/', $bundle)) {
            throw new \InvalidArgumentException('The bundle name must end with Bundle.');
        }

        return $bundle;
    }

    public static function validateTargetDir($dir, $bundle, $namespace)
    {
        // add trailing / if necessary
        return '/' === substr($dir, -1, 1) ? $dir : $dir.'/';
    }

    public static function validateFormat($format)
    {
        $format = strtolower($format);

        if (!in_array($format, array('php', 'xml', 'yml', 'annotation'))) {
            throw new \RuntimeException(sprintf('Format "%s" is not supported.', $format));
        }

        return $format;
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
