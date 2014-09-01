<?php

namespace Netgen\Bundle\GeneratorBundle\Manipulator;

use Symfony\Component\HttpKernel\KernelInterface;

class KernelManipulator extends Manipulator
{
    protected $kernel;
    protected $reflected;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->reflected = new \ReflectionObject($kernel);
    }

    /**
     * Adds a bundle at the end of the existing ones.
     *
     * @param string $bundle The bundle class name
     *
     * @return Boolean true if it worked, false otherwise
     *
     * @throws \RuntimeException If bundle is already defined
     */
    public function addBundle($bundle)
    {
        if (!$this->reflected->getFilename()) {
            return false;
        }

        $src = file($this->reflected->getFilename());
        $method = $this->reflected->getMethod('registerBundles');
        $lines = array_slice($src, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);

        // Don't add same bundle twice
        if (false !== strpos(implode('', $lines), $bundle)) {
            throw new \RuntimeException(sprintf('Bundle "%s" is already defined in "AppKernel::registerBundles()".', $bundle));
        }

        $this->setCode(token_get_all('<?php '.implode('', $lines)), $method->getStartLine());
        while ($token = $this->next()) {
            if ($token[0] !== T_RETURN) {
                continue;
            }

            if (trim(implode('',array_slice($src, $this->line - 2, 1))) == "") {
                $lines = array_merge(
                    array_slice($src, 0, $this->line - 2),
                    array(sprintf("        \$bundles[] = new \\%s();\n", $bundle)),
                    array_slice($src, $this->line - 2)
                );
            }
            else {
                $lines = array_merge(
                    array_slice($src, 0, $this->line - 1),
                    array(sprintf("        \$bundles[] = new \\%s();\n", $bundle)),
                    array_slice($src, $this->line - 1)
                );
            }

            file_put_contents($this->reflected->getFilename(), implode('', $lines));

            return true;
        }
    }
}
