<?php

namespace SPF\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SPF\Rpc\Tool\ClassMapToSdk;
use SPF\Rpc\Tool\ReflectionClassMap;
use SPF\Rpc\Tool\Tars2php\Utils;

class RpcClassMap2sdk extends Command
{
    protected function configure()
    {
        $this->setName('classmap2sdk')
            ->setDescription('automatic generate client sdk according to class map')
            ->setHelp('You can automatic generate client sdk according to class map using this command')
            ->setDefinition(
                new InputDefinition([
                    new InputOption('with-sdktools', null, InputOption::VALUE_OPTIONAL, '自动生成sdktools文件，默认为 true'),
                    new InputOption('with-tars', null, InputOption::VALUE_OPTIONAL, '自动生成tars文件，默认为 false'),
                ])
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Utils::setConsoleOutput($output);

        ReflectionClassMap::initMap();
        $classMap = ReflectionClassMap::getMap();

        $withSdkTools = $input->getOption('with-sdktools') !== 'false';
        $withTarsFiles = $input->getOption('with-tars') === 'true';

        $classMap2Sdk = new ClassMapToSdk();
        $classMap2Sdk->handle($classMap, $withSdkTools, $withTarsFiles);
    }
}
