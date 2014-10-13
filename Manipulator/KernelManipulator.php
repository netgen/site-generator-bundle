<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Manipulator;

use Symfony\Component\HttpKernel\KernelInterface;
use ReflectionObject;
use RuntimeException;

class KernelManipulator extends Manipulator
{

    /**
     * @var \Symfony\Component\HttpKernel\KernelInterface
     */
    protected $kernel;

    /**
     * @var \ReflectionObject
     */
    protected $reflected;

    /**
     * Constructor
     *
     * @param \Symfony\Component\HttpKernel\KernelInterface $kernel
     */
    public function __construct( KernelInterface $kernel )
    {
        $this->kernel = $kernel;
        $this->reflected = new ReflectionObject( $kernel );
    }

    /**
     * Adds a bundle at the end of the existing ones
     *
     * @param string $bundle The bundle class name
     *
     * @return boolean true if it worked, false otherwise
     *
     * @throws \RuntimeException If bundle is already defined
     */
    public function addBundle( $bundle )
    {
        if ( !$this->reflected->getFilename() )
        {
            return false;
        }

        $src = file( $this->reflected->getFilename() );
        $method = $this->reflected->getMethod( 'registerBundles' );
        $lines = array_slice( $src, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 );

        // Don't add same bundle twice
        if ( strpos( implode( '', $lines ), $bundle ) !== false )
        {
            throw new RuntimeException( sprintf( 'Bundle "%s" is already defined in "%s::registerBundles()".', $this->reflected->getName(), $bundle ) );
        }

        $this->setCode( token_get_all( '<?php ' . implode( '', $lines ) ), $method->getStartLine() );
        while ( $token = $this->next() )
        {
            if ( $token[0] !== T_RETURN )
            {
                continue;
            }

            if ( trim( implode( '', array_slice( $src, $this->line - 2, 1 ) ) ) == "" )
            {
                $lines = array_merge(
                    array_slice( $src, 0, $this->line - 2 ),
                    array( sprintf( "        \$bundles[] = new \\%s();\n", $bundle ) ),
                    array_slice( $src, $this->line - 2 )
                );
            }
            else
            {
                $lines = array_merge(
                    array_slice( $src, 0, $this->line - 1 ),
                    array( sprintf( "        \$bundles[] = new \\%s();\n", $bundle ) ),
                    array_slice( $src, $this->line - 1 )
                );
            }

            file_put_contents( $this->reflected->getFilename(), implode( '', $lines ) );

            return true;
        }
    }
}
