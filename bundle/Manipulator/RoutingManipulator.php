<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Manipulator;

use RuntimeException;
use Symfony\Component\DependencyInjection\Container;

class RoutingManipulator
{
    /**
     * The YAML routing file path.
     *
     * @var string
     */
    private $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    /**
     * Adds a routing resource at the top of the existing ones.
     *
     * @throws \RuntimeException If bundle is already imported
     */
    public function addResource(string $bundle, string $prefix = '/', string $path = 'routing'): bool
    {
        $current = '';
        if (file_exists($this->file)) {
            $current = file_get_contents($this->file);

            // Don't add same bundle twice
            if (strpos($current, $bundle) !== false) {
                throw new RuntimeException(sprintf('Bundle "%s" is already imported.', $bundle));
            }
        } elseif (!is_dir($dir = dirname($this->file))) {
            mkdir($dir, 0777, true);
        }

        $code = "\n" . sprintf("%s:\n", Container::underscore(substr($bundle, 0, -6)) . ('/' !== $prefix ? '_' . str_replace('/', '_', substr($prefix, 1)) : ''));
        $code .= sprintf("    resource: \"@%s/Resources/config/%s.yml\"\n", $bundle, $path);
        $code = $current . $code;

        return !(file_put_contents($this->file, $code) === false);
    }
}
