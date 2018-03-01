<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Composer;

use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as DistributionBundleScriptHandler;
use Composer\Script\Event;

class ScriptHandler extends DistributionBundleScriptHandler
{
    /**
     * Runs the Symfony console command that generates new Netgen More project.
     *
     * @param \Composer\Script\Event $event
     */
    public static function generateNetgenMoreProject(Event $event)
    {
        $options = self::getOptions($event);
        $consoleDir = static::getConsoleDir($event, 'generate Netgen More project');

        static::executeCommand($event, $consoleDir, 'ngmore:generate:project', $options['process-timeout']);
    }
}
