<?php

namespace SPF\Command;

use SPF;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopServerCmd extends Command
{
    protected function configure()
    {
        $this
            ->setName('stop')
            ->setDescription('stop server')
            ->setHelp('This command allow you to stop server');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ret = SPF\Network\Server::stop();
        if ($ret['code'] == 0) {
            $output->writeln("<info>{$ret['msg']}<info>");
        } else {
            $output->writeln("<error>{$ret['msg']}<error>");
        }
    }
}
