<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Netgen\Bundle\MoreGeneratorBundle\Command\Helper\DialogHelper;

abstract class GeneratorCommand extends ContainerAwareCommand
{
    /**
     * Returns the dialog helper
     *
     * @return \Netgen\Bundle\MoreGeneratorBundle\Command\Helper\DialogHelper
     */
    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get( 'dialog' );
        if ( !$dialog || get_class( $dialog ) !== 'Netgen\Bundle\MoreGeneratorBundle\Command\Helper\DialogHelper' )
        {
            $this->getHelperSet()->set( $dialog = new DialogHelper() );
        }

        return $dialog;
    }
}
