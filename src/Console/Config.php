<?php

namespace SugarCli\Console;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Config\Definition\Processor;

class Config implements ConfigurationInterface, HelperInterface
{
    protected $helper_set = null;

    protected $config_data = array();

    public $config_files = array();


    public function __construct($config_files = array())
    {
        $this->config_files = $config_files;
    }

    /**
     * Read configuration files and merge them in an array.
     */
    public function load()
    {
        $yaml = new Parser();
        $parsed_confs = array();
        foreach ($this->config_files as $conf) {
            if (is_readable($conf)) {
                $parsed_confs[] = $yaml->parse(file_get_contents($conf));
            }
        }
        //Validate and merge configuration.
        $processor = new Processor();
        $this->config_data = $processor->processConfiguration($this, $parsed_confs);
    }

    /**
     * Used to validate the configuration.
     */
    public function getConfigTreeBuilder()
    {
        $tree_builder = new TreeBuilder();
        $tree_builder->root('sugarcli')
        ->children()
            ->arrayNode('sugarcrm')
            ->children()
                ->scalarNode('path')->cannotBeEmpty()->end()
                ->scalarNode('url')->cannotBeEmpty()->end()
            ->end()
        ->end();
        return $tree_builder;
    }

    /**
     * Return a config value from it's path seperated by dots.
     */
    public function get($path = '', $test_only = false)
    {
        $data = $this->config_data;
        $nodes = explode('.', $path);
        foreach ($nodes as $node) {
            if ($node === '') {
                continue;
            }
            if (is_array($data) && array_key_exists($node, $data)) {
                $data = $data[$node];
            } else {
                if ($test_only) {
                    return false;
                }
                throw new ConfigException("Unknown config node $node in path $path.");
            }
        }
        if ($test_only) {
            return true;
        }
        return $data;
    }

    /**
     * Test if path exists
     */
    public function has($path = '')
    {
        return $this->get($path, true);
    }

    /**
     * Sets the helper set associated with this helper.
     *
     * @param HelperSet $helperSet A HelperSet instance
     */

    public function setHelperSet(HelperSet $helper_set = null)
    {
        $this->helper_set = $helper_set;
    }

    /**
     * Gets the helper set associated with this helper.
     *
     * @return HelperSet A HelperSet instance
     */
    public function getHelperSet()
    {
        return $this->helper_set;
    }

    /**
     * Implement the HelperInterface
     */
    public function getName()
    {
        return 'config';
    }
}
