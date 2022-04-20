<?php

namespace SPF\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use SPF\Rpc\Config;
use SPF\Rpc\Tool\Tars2php\FileConverter;
use SPF\Rpc\Tool\Tars2php\Utils;

class Tars2php extends Command
{
    protected function configure()
    {
        $this->setName('tars2php')
            ->setDescription('automatic generate structs and interfaces according to tars file')
            ->setHelp('You can automatic generate structs and interfaces according to tars file using this command')
            ->setDefinition(
                new InputDefinition([
                ])
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tarsConfig = Config::getOrFailed('app.tars');
        if (empty($tarsConfig['nsPrefix'])) {
            $tarsConfig['nsPrefix'] = Config::getOrFailed('app.namespacePrefix');
        }

        $rootPath = Config::$rootPath;

        Utils::setConsoleOutput($output);
        
        $fileConverter = new FileConverter($tarsConfig, $rootPath);
        
        $fileConverter->moduleScanRecursive();
        $fileConverter->moduleParserRecursive();
    }
}
