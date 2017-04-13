<?php
/**
 * SugarCLI
 *
 * PHP Version 5.3 -> 5.4
 * SugarCRM Versions 6.5 - 7.6
 *
 * @author Rémi Sauvat
 * @author Emmanuel Dyan
 * @copyright 2005-2015 iNet Process
 *
 * @package inetprocess/sugarcrm
 *
 * @license Apache License 2.0
 *
 * @link http://www.inetprocess.com
 */

namespace SugarCli\Console\Command;

use Inet\SugarCRM\Application as SugarApp;
use Inet\SugarCRM\System as SugarSystem;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class SystemQuickRepairCommand extends AbstractConfigOptionCommand
{
    protected $messages = array();
    const SAFE_VERSION = '7.5.0.0';

    protected function configure()
    {
        $this->setName('system:quickrepair')
             ->setDescription('Do a quick repair and rebuild.')
             ->enableStandardOption('path')
             ->enableStandardOption('user-id')
             ->addOption(
                 'no-database',
                 null,
                 InputOption::VALUE_NONE,
                 'Do not manage database changes.'
             )
             ->addOption(
                 'force',
                 'f',
                 InputOption::VALUE_NONE,
                 'Really execute the SQL queries (displayed by using -d).'
             )
             ->addOption(
                 'rm-cache',
                 'r',
                 InputOption::VALUE_NONE,
                 'Remove the cache folder and all it\'s contents before the repair'
             );
    }

    public function isRemoveCacheSafe(SugarApp $sugar_app)
    {
        $version_array = $sugar_app->getVersion();
        return version_compare($version_array['version'], self::SAFE_VERSION, '>=');
    }

    public function removeCache($sugar_app)
    {
        if (!$this->isRemoveCacheSafe($sugar_app)) {
            $this->getService('logger')->warning(
                'Your version of SugarCRM do not support safe suppression of the cache folder.'
            );
            return;
        }

        $fs = new Filesystem();
        $cache_path = $sugar_app->getPath() . '/cache';
        if ($fs->exists($cache_path)) {
            $fs->remove($cache_path);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $output->writeln('<comment>Reparation</comment>: ');
        $progress = new ProgressIndicator($output);

        $progress->start('Starting...');
        $progress->advance();

        $sugar_app = $this->getService('sugarcrm.application');
        if ($input->getOption('rm-cache')) {
            $progress->setMessage('Removing cache...');
            $progress->advance();
            $this->removeCache($sugar_app);
        }

        $fs = new Filesystem();
        $fs->mkdir($sugar_app->getPath() . '/cache');

        $progress->setMessage('Working...');
        $sugarEP = $this->getService('sugarcrm.entrypoint');
        $sugarSystem = new SugarSystem($sugarEP);
        $messages = $sugarSystem->repairAll($input->getOption('force'), $input->getOption('user-id'));
        $progress->finish('<info>Repair Done.</info>');

        if ($output->isVerbose()) {
            $output->writeln(PHP_EOL . '<comment>General Messages</comment>: ');
            $output->writeln($messages[0]);
        }

        if ($input->getOption('no-database') === true) {
            return;
        }

        $output->writeln(PHP_EOL . '<comment>Database Messages</comment>: ');
        // We have something to sync
        if (strpos($messages[1], 'Database tables are synced with vardefs') !== 0) {
            if ($input->getOption('force') === false) {
                $output->writeln($messages[1]);
                $output->writeln(PHP_EOL . '<error>You need to use --force to run the queries</error>');
            } else {
                $output->writeln('<info>Queries run, try another repair to verify</info>');
            }
        // Nothing to sync, default sugar message
        } else {
            $output->writeln($messages[1]);
        }
    }
}
