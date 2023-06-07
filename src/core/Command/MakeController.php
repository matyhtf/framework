<?php
namespace SPF\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeController extends Command
{
    protected function configure()
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'Who do you want to greet?'
        );
        $this->setName('make:controller');
        $this->setHelp("make:controller \$controler name");
        $this->setDescription("Create a new controller.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $input->getArguments();
        $name = ucfirst($args['name']);
        if (!is_dir(\SPF\App::getInstance()->app_path.'/controllers')) {
            MakeApplication::init(\SPF\App::getInstance()->app_path);
        }
        $file = \SPF\App::getInstance()->app_path . '/controllers/' . $name . '.php';
        if (is_file($file)) {
            $output->writeln("<error>Controller[$name](file=$file) already exists!</error>");
        } elseif (self::init($name, $file)) {
            $output->writeln("<info>success!</info>");
        } else {
            $output->writeln("<error>file_put_content($file) failed.!</error>");
        }
    }

    public static function init($name, $file)
    {
        $code = "<?php\nnamespace App\\Controller;\n\n";
        $code .= "use SPF\\Controller;\n\n";
        $code .= "class $name extends Controller\n{\n\n}";
        return file_put_contents($file, $code);
    }
}
