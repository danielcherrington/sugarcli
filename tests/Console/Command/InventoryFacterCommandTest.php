<?php
namespace SugarCli\Tests\Console\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;


use SugarCli\Console\Application;
use SugarCli\Console\Command\InventoryFacterCommand;

class InventoryFacterCommandTest extends \PHPUnit_Framework_TestCase
{
    public function getFakeSugarPath()
    {
        return __DIR__ . '/metadata/fake_sugar';
    }

    public function getCommandTester()
    {
        $app = new Application();
        $app->configure();
        $cmd = $app->find('inventory:facter');
        return new CommandTester($cmd);
    }

    /**
     * @group sugar
     */
    public function testDefault()
    {
        $cmd = $this->getCommandTester();
        $cmd->execute(array(
            '--path' => getenv('SUGARCLI_SUGAR_PATH'),
        ));

        $output = $cmd->getDisplay();
        $json = json_decode($output, true);
        $this->assertArrayHasKey('system', $json);
        $this->assertNotEmpty($json['system']);
        $this->assertArrayHasKey('sugarcrm', $json);
    }

    public function testInvalidFormat()
    {
        $cmd = $this->getCommandTester();
        $cmd->execute(
            array(
                '--format' => 'abc',
                'source' => array('system'),
            )
        );
        $this->assertEquals(3, $cmd->getStatusCode());
    }

    public function testSugarcrmOnly()
    {
        $cmd = $this->getCommandTester();
        $cmd->execute(array(
            '--format' => 'json',
            '--path' => getenv('SUGARCLI_SUGAR_PATH'),
            'source' => array('sugarcrm')
        ));

        $output = $cmd->getDisplay();
        $json = json_decode($output, true);
        $this->assertArrayHasKey('system', $json);
        $this->assertArrayHasKey('sugarcrm', $json);
        $this->assertEmpty($json['system']);
        $this->assertNotEmpty($json['sugarcrm']);
    }

    public function testXmlFormat()
    {
        $cmd = $this->getCommandTester();
        $cmd->execute(array('--format' => 'xml', 'source' => array('system')));

        $output = $cmd->getDisplay();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $output);
    }

    public function testYmlFormat()
    {
        $cmd = $this->getCommandTester();
        $cmd->execute(array(
            '--format' => 'yml',
            'source' => array('system'),
            '--custom-fact' => array('system.context:dev'),
        ));

        $output = $cmd->getDisplay();
        $yml = Yaml::parse($output);
        $this->assertArrayHasKey('system', $yml);
        $this->assertArrayHasKey('sugarcrm', $yml);
        $this->assertNotEmpty($yml['system']);
        $this->assertEquals('dev', $yml['system']['context']);
    }
}
