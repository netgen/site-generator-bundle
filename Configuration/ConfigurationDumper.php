<?php

namespace Netgen\Bundle\GeneratorBundle\Configuration;

use Sensio\Bundle\DistributionBundle\Configurator\Configurator;
use eZ\Publish\Core\MVC\Symfony\ConfigDumperInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ConfigurationDumper implements ConfigDumperInterface
{
    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fileSystem;

    /**
     * @var \Sensio\Bundle\DistributionBundle\Configurator\Configurator
     */
    protected $sensioConfigurator;

    /**
     * Path to root dir (kernel.root_dir)
     *
     * @var string
     */
    protected $rootDir;

    /**
     * Path to cache dir (kernel.cache_dir)
     *
     * @var string
     */
    protected $cacheDir;

    /**
     * Name of the admin siteaccess
     *
     * @var string
     */
    protected $adminSiteAccess;

    /**
     * Name of the bundle
     *
     * @var string
     */
    protected $bundleName;

    /**
     * Set of environments to pre-generate config file for. Key is the environment name.
     *
     * @var array
     */
    protected $environments;

    public function __construct( Filesystem $fileSystem, array $environments, $rootDir, $cacheDir, $adminSiteAccess, $bundleName, Configurator $sensioConfigurator )
    {
        $this->fileSystem = $fileSystem;
        $this->rootDir = $rootDir;
        $this->cacheDir = $cacheDir;
        $this->environments = array_fill_keys( $environments, true );
        $this->adminSiteAccess = $adminSiteAccess;
        $this->bundleName = $bundleName;
        $this->sensioConfigurator = $sensioConfigurator;
    }

    /**
     * Adds an environment to dump a configuration file for.
     *
     * @param string $environment
     */
    public function addEnvironment( $environment )
    {
        $this->environments[$environment] = true;
    }

    /**
     * Dumps settings contained in $configArray in ezpublish.yml
     *
     * @param array $configArray Hash of settings.
     * @param int $options A binary combination of options. See class OPT_* class constants in {@link \eZ\Publish\Core\MVC\Symfony\ConfigDumperInterface}
     *
     * @return void
     */
    public function dump( array $configArray, $options = ConfigDumperInterface::OPT_DEFAULT )
    {
        $configPath = "$this->rootDir/config";
        $mainConfigFile = "$configPath/ezpublish.yml";
        if ( $this->fileSystem->exists( $mainConfigFile ) && $options & static::OPT_BACKUP_CONFIG )
        {
            $this->backupConfigFile( $mainConfigFile );
        }

        // We will transfer siteaccess match settings to environment specific files
        $siteAccessMatchSettings = $configArray['ezpublish']['siteaccess']['match'];
        unset( $configArray['ezpublish']['siteaccess']['match'] );

        file_put_contents( $mainConfigFile, Yaml::dump( $configArray, 7 ) );

        // Now generates environment config files
        foreach ( array_keys( $this->environments ) as $environment )
        {
            $configFile = "$configPath/ezpublish_{$environment}.yml";
            // Add the import statement for the root YAML file
            $envConfigArray = array(
                'imports' => array(
                    array( 'resource' => 'ezpublish.yml' )
                )
            );

            $envConfigArray['doctrine'] = $doctrineSettings;
            $envConfigArray['ezpublish']['siteaccess']['match'] = $siteAccessMatchSettings;

            // File already exists, handle possible options
            if ( $this->fileSystem->exists( $configFile ) && $options & static::OPT_BACKUP_CONFIG )
            {
                $this->backupConfigFile( $configFile );
            }

            file_put_contents( $configFile, Yaml::dump( $envConfigArray, 14 ) );
        }

        // Now generate netgen more config file

        $netgenMoreConfigArray = array();
        $netgenMoreConfigArray['ez_publish_legacy'] = array();
        $netgenMoreConfigArray['ez_publish_legacy']['system'] = array();

        foreach ( $configArray['ezpublish']['siteaccess']['list'] as $siteAccess )
        {
            if ( $siteAccess !== $this->adminSiteAccess )
            {
                $netgenMoreConfigArray['ez_publish_legacy']['system'][$siteAccess] = array();
                $netgenMoreConfigArray['ez_publish_legacy']['system'][$siteAccess]['templating'] = array(
                    'view_layout' => $this->bundleName . '::pagelayout_legacy.html.twig',
                    'module_layout' => $this->bundleName . '::pagelayout_module.html.twig'
                );
            }
        }

        $netgenMoreConfigFile = "$configPath/ngmore.yml";

        // File already exists, handle possible options
        if ( $this->fileSystem->exists( $netgenMoreConfigFile ) && $options & static::OPT_BACKUP_CONFIG )
        {
            $this->backupConfigFile( $netgenMoreConfigFile );
        }

        file_put_contents( $netgenMoreConfigFile, Yaml::dump( $netgenMoreConfigArray, 7 ) );

        // Handling %secret%
        $this->sensioConfigurator->mergeParameters(
            array(
                // Step #1 is SecretStep
                'secret' => $this->sensioConfigurator->getStep( 1 )->secret
            )
        );
        $this->sensioConfigurator->write();

        $this->clearCache();
    }

    /**
     * Makes a backup copy of $configFile.
     *
     * @param string $configFile
     *
     * @return void
     */
    protected function backupConfigFile( $configFile )
    {
        if ( $this->fileSystem->exists( $configFile ) )
            $this->fileSystem->copy( $configFile, $configFile . '-' . date( 'Y-m-d_H-i-s' ) );
    }

    /**
     * Clears the configuration cache.
     */
    protected function clearCache()
    {
        $oldCacheDirName = "{$this->cacheDir}_old";
        $this->fileSystem->rename( $this->cacheDir, $oldCacheDirName );
        $this->fileSystem->remove( $oldCacheDirName );
    }
}
