<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Composer;

use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as DistributionBundleScriptHandler;
use Composer\Script\CommandEvent;

class ScriptHandler extends DistributionBundleScriptHandler
{
    /**
     * Runs the Symfony console command that generates new Netgen More project
     *
     * @param \Composer\Script\CommandEvent $event
     */
    public static function generateNetgenMoreProject( CommandEvent $event )
    {
        $options = self::getOptions( $event );
        $appDir = $options['symfony-app-dir'];

        if ( !is_dir( $appDir ) )
        {
            echo 'The symfony-app-dir (' . $appDir . ') specified in composer.json was not found in ' . getcwd() . ', can not generate project.' . PHP_EOL;
            return;
        }

        static::executeCommand( $event, $appDir, 'ngmore:generate:project', $options['process-timeout'] );
    }
}
