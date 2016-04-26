<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Composer;

use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as DistributionBundleScriptHandler;
use Composer\Script\Event;
use eZ\Bundle\EzPublishCoreBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Debug\Debug;
use EzPublishKernel;

class ScriptHandler extends DistributionBundleScriptHandler
{
    /**
     * Runs the Symfony console command that generates new Netgen More project.
     *
     * @param \Composer\Script\Event $event
     */
    public static function generateNetgenMoreProject(Event $event)
    {
        require_once getcwd() . '/ezpublish/autoload.php';
        require_once getcwd() . '/ezpublish/EzPublishKernel.php';

        $input = new ArrayInput(
            array(
                'command' => 'ngmore:generate:project',
            )
        );

        $env = $input->getParameterOption(array('--env', '-e'), getenv('SYMFONY_ENV') ?: 'dev');
        $debug = getenv('SYMFONY_DEBUG') !== '0' && !$input->hasParameterOption(array('--no-debug', '')) && $env !== 'prod';
        if ($debug) {
            Debug::enable();
        }

        $application = new Application(new EzPublishKernel($env, $debug));
        $application->run($input);
    }
}
