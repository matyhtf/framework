<?php

namespace SPF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SPF\Exceptions\InvalidArgumentException;
use SPF\Generator\RpcTests;
use Throwable;

/**
 * Automatic generate rpc test case command.
 */
class GenerateRpcTests extends Command
{
    protected function configure()
    {
        $this->setName('generate:rpc:tests')
            ->setDescription('automatic generate test cases')
            ->setHelp('You can automatic generate test cases using this command')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('source', 's', InputOption::VALUE_OPTIONAL, '要生产SDK的源码目录，默认为 src'),
                    new InputOption('output', 'o', InputOption::VALUE_OPTIONAL, 'SDK输出目录，默认为 tests'),
                    new InputOption(
                        'mode',
                        'm',
                        InputOption::VALUE_OPTIONAL,
                        '测试用例文件覆盖方式：replace(r)-新文件覆盖老文件；skip(s)-若老文件存在新文件自动跳过；'.
                        'backup(b)-备份老文件，然后覆盖新文件；confirm(c)-手动选择文件覆盖方式，默认为confirm(c)'
                    ),
                ))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $src = $this->resolvePath($this->getOption($input, 'source', 'src'));
        $target = $this->resolvePath($this->getOption($input, 'output', 'tests'));
        $mode = $this->resolveMode($this->getOption($input, 'mode', 'confirm'));

        $this->validSource($src);

        $output->writeln(["<info>Select source path: $src<info>"]);
        $output->writeln("<info>Select output path: $target<info>");
        $output->writeln("<info>Select mode: $mode<info>");
        
        $generator = new RpcTests($output, $io);
        try {
            $generator->handle($src, $target, $mode);
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            foreach (explode("\n", $e->getTraceAsString()) as $line) {
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
     * Resolve repeat file process mode.
     *
     * @param string $mode
     *
     * @return string
     */
    protected function resolveMode($mode)
    {
        switch ($mode) {
            case 'confirm':
            case 'c':
                return 'confirm';
            case 'replace':
            case 'r':
                return 'replace';
            case 'skip':
            case 's':
                return 'skip';
            case 'backup':
            case 'b':
                return 'backup';
            default:
                throw new InvalidArgumentException("option [mode] must be in 'confirm, replace, skip, backup'.");
        }
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
