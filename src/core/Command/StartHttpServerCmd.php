<?php

namespace SPF\Command;

use SPF;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartHttpServerCmd extends Command
{
    protected function configure()
    {
        $this
            ->setName('start:http')
            ->setDescription('start HTTP server')
            ->setHelp('This command allow you to start a HTTP server')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('host', 'host', InputOption::VALUE_OPTIONAL, '指定监听地址'),
                    new InputOption('port', 'p', InputOption::VALUE_OPTIONAL, '指定监听端口'),
                    new InputOption(
                        'daemon',
                        'd',
                        InputOption::VALUE_OPTIONAL,
                        '启用守护进程模式'
                    ),
                    new InputOption(
                        'base',
                        'b',
                        InputOption::VALUE_OPTIONAL,
                        '使用BASE模式启动'
                    ),
                    new InputOption(
                        'worker',
                        'w',
                        InputOption::VALUE_OPTIONAL,
                        '设置Worker进程的数量'
                    ),
                    new InputOption(
                        'thread',
                        'r',
                        InputOption::VALUE_OPTIONAL,
                        '设置Reactor线程的数量'
                    ),
                    new InputOption(
                        'task',
                        't',
                        InputOption::VALUE_OPTIONAL,
                        '设置Task进程的数量'
                    ),
                ))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("<info>Starting server...<info>");
        $options = $input->getOptions();
        self::addServerOption('host', $options);
        self::addServerOption('port', $options);
        self::addServerOption('daemon', $options);
        self::addServerOption('base', $options);
        self::addServerOption('worker', $options);
        self::addServerOption('thread', $options);
        self::addServerOption('task', $options);
        $ret = SPF\Network\Server::startServer(true);
        if ($ret['code'] != 0) {
            $output->writeln("<error>{$ret['msg']}<error>");
        }
    }

    public static function addServerOption($key, $options)
    {
        if (!empty($options[$key])) {
            SPF\Network\Server::setOption($key, $options[$key]);
        }
    }
}
