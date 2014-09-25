<?php

namespace Netgen\Bundle\GeneratorBundle\Manipulator;

use Symfony\Component\DependencyInjection\Container;
use RuntimeException;

class RoutingManipulator extends Manipulator
{
    /**
     * @var string
     */
    private $file;

    /**
     * Constructor
     *
     * @param string $file The YAML routing file path
     */
    public function __construct( $file )
    {
        $this->file = $file;
    }

    /**
     * Adds a routing resource at the top of the existing ones
     *
     * @param string $bundle
     * @param string $format
     * @param string $prefix
     * @param string $path
     *
     * @return boolean true if it worked, false otherwise
     *
     * @throws \RuntimeException If bundle is already imported
     */
    public function addResource( $bundle, $format, $prefix = '/', $path = 'routing' )
    {
        $current = '';
        if ( file_exists( $this->file ) )
        {
            $current = file_get_contents( $this->file );

            // Don't add same bundle twice
            if ( strpos( $current, $bundle ) !== false )
            {
                throw new RuntimeException( sprintf( 'Bundle "%s" is already imported.', $bundle ) );
            }
        }
        else if ( !is_dir( $dir = dirname( $this->file ) ) )
        {
            mkdir( $dir, 0777, true );
        }

        $code = "\n" . sprintf( "%s:\n", Container::underscore( substr( $bundle, 0, -6 ) ) . ( '/' !== $prefix ? '_' . str_replace( '/', '_', substr( $prefix, 1 ) ) : '' ) );
        if ( $format == 'annotation' )
        {
            $code .= sprintf( "    resource: \"@%s/Controller/\"\n    type: annotation\n", $bundle );
        }
        else
        {
            $code .= sprintf( "    resource: \"@%s/Resources/config/%s.%s\"\n", $bundle, $path, $format );
        }
        $code = $current . $code;

        if ( file_put_contents( $this->file, $code ) === false )
        {
            return false;
        }

        return true;
    }
}
