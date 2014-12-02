<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command\Helper;

use Symfony\Component\Console\Helper\DialogHelper as BaseDialogHelper;
use Symfony\Component\Console\Output\OutputInterface;

class DialogHelper extends BaseDialogHelper
{
    /**
     * Returns a formatted question
     *
     * @param string $question
     * @param boolean $default
     * @param string $sep
     *
     * @return string
     */
    public function getQuestion( $question, $default, $sep = ':' )
    {
        return $default ?
            sprintf( '<info>%s</info> [<comment>%s</comment>]%s ', $question, $default, $sep ) :
            sprintf( '<info>%s</info>%s ', $question, $sep );
    }
}
