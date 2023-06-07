<?php
namespace SPF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SPF\Core;

class MakeConfig extends Command
{
    protected function configure()
    {
        $this->addArgument(
            'type',
            InputArgument::REQUIRED,
            'What is your configuration type?'
        )->addArgument(
            'name',
            InputArgument::OPTIONAL,
            'What is your configuration name?'
        );

        $this->setName('make:config');
        $this->setHelp("make:config type(db, redis, cache, etc.) [name](master, slave, etc.)");
        $this->setDescription("Create a new config.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $input->getArguments();
        $type = strtolower($args['type']);
        $name = strtolower($args['name']);
        if (!is_dir(\SPF\App::getInstance()->app_path.'/configs')) {
            MakeApplication::init(\SPF\App::getInstance()->app_path);
        }
        $file = \SPF\App::getInstance()->app_path . '/configs/' . $type . '.php';
        if (is_file($file) and empty($name)) {
            $output->writeln("<error>Config[{$type}](file={$file}) already exists!</error>");

            return;
        }
        if (!is_file($file) and !self::init($type, $file)) {
            _write_error:
            $output->writeln("<error>file_put_content($file) failed.!</error>");

            return;
        }

        if (!empty($name)) {
            $config = Core::getInstance()->config[$type];
            if (isset($config[$name])) {
                _exists:
                $output->writeln("<error>Config[{$type}][$name] already exists!</error>");
                return;
            }
        } else {
            $config = array();
        }

        switch ($type) {
            case 'redis':
                $config[$name] = array(
                    'host' => "127.0.0.1",
                    'port' => 6379,
                    'password' => '',
                    'timeout' => 0.25,
                    'pconnect' => false,
                    'database' => 0,
                );
                break;

            case 'db':
                $config[$name] = array(
                    'type' => \SPF\Database::TYPE_MYSQLi,
                    'host' => "127.0.0.1",
                    'port' => 3306,
                    'dbms' => 'mysql',
                    'user' => "{user}",
                    'passwd' => "{passwd}",
                    'name' => "{name}",
                    'charset' => "utf8",
                    'setname' => true,
                    'persistent' => false,
                );
                break;
            default:
                break;
        }

        if (file_put_contents($file, "<?php\nreturn " . var_export($config, true) . ";\n")) {
            $output->writeln("<info>success!</info>");

            return;
        } else {
            goto _write_error;
        }
    }

    public static function init($name, $file)
    {
        $code = "<?php\nreturn array(\n\t\n);";
        return file_put_contents($file, $code);
    }
}
