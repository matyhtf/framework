<?php
namespace SPF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeModel extends Command
{
    protected function configure()
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'Who do you want to greet?'
        );
        $this->addArgument(
            'table',
            InputArgument::REQUIRED,
            'Who do you want to greet?'
        );
        $this->setName('make:model');
        $this->setHelp("make:model \$model name");
        $this->setDescription("Create a new model.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $input->getArguments();
        $name = ucfirst($args['name']);
        $table =  strtolower($args['table']);
        if (!is_dir(\SPF\App::getInstance()->app_path.'/models'))
        {
            MakeApplication::init(\SPF\App::getInstance()->app_path);
        }
        $file = \SPF\App::getInstance()->app_path . '/models/' . $name . '.php';
        if (is_file($file))
        {
            $output->writeln("<error>Model[$name] already exists!</error>");
        }
        elseif (self::init($name, $table, $file))
        {
            $output->writeln("<info>success!</info>");
        }
        else
        {
            $output->writeln("<error>file_put_content($file) failed.!</error>");
        }
    }

    static function init($name, $table, $file)
    {
        $code = "<?php\nnamespace App\\Model;\n\n";
        $code .= "use SPF\\Model;\n\n";
        $code .= "class $name extends Model\n{\n\tpublic \$table = '{$table}';\n\n}";
        return file_put_contents($file, $code);
    }
}