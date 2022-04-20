<?php

namespace SPF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SPF\Exception\InvalidArgumentException;
use SPF\Publish\RpcSdk;
use Throwable;

class PublishRpcSdk extends Command
{
    protected function configure()
    {
        $this->setName('publish:rpc:sdk')
            ->setDescription('automatic generate rpc sdk project')
            ->setHelp('You can automatic generate rpc sdk project using this command')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('path', 'p', InputOption::VALUE_REQUIRED, 'SDK源码路径，默认为 sdk'),
                ))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $path = $this->resolvePath($this->getOption($input, 'path', 'sdk'));

        $this->validPath($path);

        $output->writeln("<info>Select sdk path: $path<info>");
        
        $publisher = new RpcSdk($output, $io);
        try {
            $publisher->handle($path);
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
     * Validate the path`s value
     * 
     * @param string $path
     */
    protected function validPath($path)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("option path`s value [$path] is not a directory");
        }
    }
}
