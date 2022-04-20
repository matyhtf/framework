<?php

namespace SPF\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Command extends BaseCommand
{
    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * 拦截注入属性$input、$output
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        try {
            return parent::run($input, $output);
        } catch (Throwable $e) {
            $this->renderException($e);

            return 1;
        }
    }

    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    protected function output()
    {
        return $this->output;
    }

    /**
     * 输出Raw信息到终端（不换行）
     * 
     * @param string $msg
     */
    protected function write($msg)
    {
        $this->output()->write($msg);
    }

    /**
     * 输出Raw信息到终端
     * 
     * @param string $msg
     */
    protected function writeln($msg)
    {
        $this->output()->writeln($msg);
    }

    /**
     * 输出INFO信息到终端
     * 
     * @param string $msg
     */
    protected function info($msg)
    {
        $this->writeln("<info>{$msg}</info>");
    }

    /**
     * 输出错误信息到终端
     * 
     * @param string $msg
     */
    protected function error($msg)
    {
        $this->writeln("<error>{$msg}</error>");
    }

    /**
     * 输出备注信息到终端
     * 
     * @param string $msg
     */
    protected function comment($msg)
    {
        $this->writeln("<comment>{$msg}</comment>");
    }

    /**
     * 输出表格到终端
     * 
     * @param array $header [A, B]
     * @param array $body [[R11, R12], [R21, R22], ...]
     */
    protected function table(array $header, array $body)
    {
        $table = new Table($this->output());
        $table->setHeaders($header);
        foreach($body as $row) {
            $table->addRow($row);
        }
        $table->render();
    }

    protected function renderException(Throwable $e)
    {
        $code = $e->getCode();
        $msg = $e->getMessage();
        $this->error(">Exception({$code}): {$msg}");
        foreach(explode("\n", $e->getTraceAsString()) as $line) {
            $this->info("  >{$line}");
        }
    }
}
