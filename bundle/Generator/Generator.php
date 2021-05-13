<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Generator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;

abstract class Generator
{
    public const EZPLATFORM_ADMIN_SITEACCESS_NAME = 'admin';
    public const NGADMINUI_SITEACCESS_NAME = 'ngadminui';
    public const LEGACY_ADMIN_SITEACCESS_NAME = 'legacy_admin';

    protected string $skeletonDir;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Sets the directory to look for templates.
     */
    public function setSkeletonDir(string $skeletonDir): void
    {
        $this->skeletonDir = $skeletonDir;
    }

    /**
     * Renders the template.
     */
    protected function render(string $template, array $parameters): string
    {
        $twig = new Environment(
            new FilesystemLoader([$this->skeletonDir]),
            [
                'debug' => true,
                'cache' => false,
                'strict_variables' => true,
                'autoescape' => false,
            ],
        );

        return $twig->render($template, $parameters);
    }

    /**
     * Renders the template to a file.
     */
    protected function renderFile(string $template, string $target, array $parameters)
    {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        return file_put_contents($target, $this->render($template, $parameters));
    }
}
