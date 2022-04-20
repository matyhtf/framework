<?php

namespace SPF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SPF\Exception\InvalidArgumentException;
use SPF\Generator\RpcSdk;
use Throwable;

class GenerateRpcSdk extends Command
{
    protected function configure()
    {
        $this->setName('generate:rpc:sdk')
            ->setDescription('automatic generate rpc sdk project')
            ->setHelp('You can automatic generate rpc sdk project using this command')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('source', 's', InputOption::VALUE_REQUIRED, '要生产SDK的源码目录，默认为 src/api'),
                    new InputOption('output', 'o', InputOption::VALUE_OPTIONAL, 'SDK输出目录，默认为 sdk'),
                    new InputOption('libdir', null, InputOption::VALUE_OPTIONAL, 'SDK输出lib子目录，默认为 src'),
                ))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $src = $this->resolvePath($this->getOption($input, 'source', 'src/api'));
        $target = $this->resolvePath($this->getOption($input, 'output', 'sdk'));
        $libdir = $this->getOption($input, 'libdir', 'src');

        $this->validSource($src);

        $output->writeln("<info>Select source path: $src<info>");
        $output->writeln("<info>Select output path: $target<info>");
        $output->writeln("<info>Select libs directory: $libdir<info>");
        
        $generator = new RpcSdk($output);
        try {
            $generator->handle($src, $target, $libdir);
        } catch (Throwable $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            foreach(explode("\n", $e->getTraceAsString()) as $line) {
                $output->writeln("<comment>  {$line}</comment>");
            }
        }
    }

    /**
     * Get option value from input.
     * 
     * @param InputInterface $input
     * @param string $name option`s name
     * @param string $default default value if the option null
     * 
     * @return string
     */
    protected function getOption(InputInterface $input, $name, $default = null)
    {
        $option = $input->getOption($name);
        if (is_null($option) && is_null($default)) {
            throw new InvalidArgumentException("option [$name] cannot be empty");
        }

        return $option ?: $default;
    }

    /**
     * Resolve path to full path.
     * 
     * @param string $path
     * 
     * @return string
     */
    protected function resolvePath($path)
    {
        $rootPath = defined('ROOT_PATH') ? ROOT_PATH : getcwd();

        return mb_substr($path, 0, 1) == '/' ? $path : $rootPath.'/'.$path;
    }

    /**
     * Validate the source`s value
     * 
     * @param string $src
     */
    protected function validSource($src)
    {
        if (!is_dir($src)) {
            throw new InvalidArgumentException("option source`s value [$src] is not a directory");
        }
    }
}
