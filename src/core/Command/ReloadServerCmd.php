<?php

namespace SPF\Command;

use SPF;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReloadServerCmd extends Command
{
    protected function configure()
    {
        $this
            ->setName('reload')
            ->setDescription('reload server')
            ->setHelp('This command allow you to reload server');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ret = SPF\Network\Server::reload();
        if ($ret['code'] == 0) {
            $output->writeln("<info>{$ret['msg']}<info>");
        } else {
            $output->writeln("<error>{$ret['msg']}<error>");
        }
    }
}
