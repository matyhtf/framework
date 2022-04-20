<?php

namespace SPF\Publish;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SPF\Exception\LogicException;
use SPF\Validator\ValidateRules;

class RpcSdk
{
    /**
     * Symfony console output instance.
     * 
     * @var OutputInterface
     */
    protected $output = null;

    /**
     * Symfony console style instance.
     * 
     * @var SymfonyStyle
     */
    protected $io = null;

    /**
     * Sdk root path.
     * 
     * @var string
     */
    protected $rootPath = null;

    /**
     * Packagist domain.
     * 
     * @var string
     */
    protected static $packagistDomain = 'https://packagist.org';

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output, SymfonyStyle $io)
    {
        $this->output = $output;

        $this->io = $io;
    }

    /**
     * Set packagist domain.
     * 
     * @param string $domain
     */
    public static function setPackagistDomain($domain)
    {
        static::$packagistDomain = $domain;
    }

    /**
     * Handle.
     * 
     * @param string $root
     */
    public function handle($root)
    {
        $this->rootPath = $root;

        $this->writeln("<info>start publish sdk.<info>");

        $this->checkComposerFile();
        
        $this->commitToGit();

        $this->updatePackagist();
    }

    protected function checkComposerFile()
    {
        if (!file_exists($this->rootPath . '/composer.json')) {
            throw new LogicException('composer.json文件不存在，无法发布');
        }
    }

    protected function commitToGit()
    {
        $output = null;
        $retVal = null;

        // if not git repo, need git init
        if (!is_dir($this->rootPath.'/.git')) {
            exec("cd {$this->rootPath} && git init", $output, $retVal);
            if ($retVal !== 0) {
                throw new LogicException('Git初始化异常');
            }
        }

        // check remote.origin.url
        exec("cd {$this->rootPath} && git config remote.origin.url", $output, $retVal);
        if ($retVal !== 0) {
            // need set remote.origin.url
            $this->writeln('<comment>未设置Git仓库地址</comment>');
            while(true) {
                $origin = $this->getIo()->ask('请填写SDK Git仓库地址');
                if (!$origin) {
                    $this->writeln('<error>SDK Git仓库地址不能为空</error>');
                    continue;
                }
                exec("cd {$this->rootPath} && git remote add origin '{$origin}'", $output, $retVal);
                if ($retVal !== 0) {
                    throw new LogicException('设置Git仓库地址异常');
                }
                break;
            }
        }

        // check user.email && user.name
        exec("cd {$this->rootPath} && git config user.name", $output, $retVal);
        if ($retVal !== 0) {
            $this->writeln('<comment>未设置Git用户名</comment>');
            while (true) {
                $username = $this->getIo()->ask('请填写Git用户名');
                if (!$username) {
                    $this->writeln('<error>Git用户名不能为空</error>');
                    continue;
                }
                exec("cd {$this->rootPath} && git config user.name '{$username}'", $output, $retVal);
                if ($retVal !== 0) {
                    throw new LogicException('设置Git用户名异常');
                }
                break;
            }
            while (true) {
                $email = $this->getIo()->ask('请填写Git用户邮箱', "{$username}@100tal.com");
                if (!$email) {
                    $this->writeln('<error>Git用户邮箱不能为空</error>');
                    continue;
                }
                if (ValidateRules::validateEmail([], $email) === false) {
                    $this->writeln('<error>邮箱格式不正确</error>');
                    continue;
                }
                exec("cd {$this->rootPath} && git config user.email '{$email}'", $output, $retVal);
                if ($retVal !== 0) {
                    throw new LogicException('设置Git用户邮箱异常');
                }
                break;
            }
        }

        // check git status
        $output = null;
        exec("cd {$this->rootPath} && git add . && git status", $output, $retVal);
        if ($retVal !== 0) {
            throw new LogicException('提交Git异常');
        }
        $this->consoleExecOutput($output);

        // check whether need commit
        if (strpos(implode("\n", $output), 'nothing') === false) {
            // write commit message
            $msg = $this->getIo()->ask('请填写commit信息', '');
            exec("cd {$this->rootPath} && git commit -m '{$msg}'", $output, $retVal);
            if ($retVal !== 0) {
                throw new LogicException('Git Commit异常');
            }
        }

        $output = null;
        exec("cd {$this->rootPath} && git add . && git cherry -v", $output, $retVal);
        if (!empty($output)) {
            // git push
            $output = null;
            exec("cd {$this->rootPath} && git branch -r", $output, $retVal);
            if (empty($output)) {
                // first commit
                exec("cd {$this->rootPath} && git push -u origin master", $output, $retVal);
                $this->consoleExecOutput($output);
            } else {
                $output = null;
                exec("cd {$this->rootPath} && git push", $output, $retVal);
                $this->consoleExecOutput($output);
            }
        }

        exec("cd {$this->rootPath} && git add . && if [[ $(git rev-list --tags --max-count=1) == $(git rev-list --remotes --max-count=1) ]]; then exit 1; else exit 0; fi", $output, $retVal);
        if ($retVal !== 0) {
            $this->writeln('<comment>当前Commit与当前Tag标签一致，无需打新的tag');
            return ;
        }

        // mark tag
        $output = null;
        $firstCommit = false;
        exec("cd {$this->rootPath} && git describe --tags $(git rev-list --tags --max-count=1 2>&1) 2>&1", $output, $retVal);
        if ($retVal !== 0) {
            // no tags
            $defaultTag = 'v0.0.1';
            $firstCommit = true;
        } else {
            // the tag should increment one on last position
            $defaultTag = explode('.', array_pop($output));
            $last = array_pop($defaultTag);
            $last += 1;
            array_push($defaultTag, $last);
            $defaultTag = implode('.', $defaultTag);
        }
        $tag = $this->getIo()->ask('请填写Git Commit的tag名称', $defaultTag);
        $msg = $this->getIo()->ask('请填写Git Commit的tag信息', '');
        $output = null;
        exec("cd {$this->rootPath} && git tag -a '{$tag}' -m '{$msg}' && git push --tag", $output, $retVal);
        if ($retVal !== 0) {
            throw new LogicException('Git Tag异常');
        }
        $this->consoleExecOutput($output);

        // first commit need init packagist
        if ($firstCommit) {
            $this->writeln('<comment>首次发布需要初始化packagist</comment>');
        }
    }

    /**
     * @param array $output
     */
    protected function consoleExecOutput($output)
    {
        if (empty($output)) {
            return ;
        }

        foreach ($output as $line) {
            $this->writeln("<info>{$line}</info>");
        }
    }

    protected function updatePackagist()
    {
        $output = null;
        $retVal = null;
        $cacheFile = "{$this->rootPath}/.git/packagist";

        if (!is_file($cacheFile)) {
            exec("cd {$this->rootPath} && git config user.name", $output, $retVal);
            $defaultName = count($output) > 0 ? array_pop($output) : null;
            while(true) {
                $username = $this->getIo()->ask('请填写Packagist帐户名称', $defaultName);
                if (empty($username)) {
                    $this->writeln('<error>帐户名称不能为空</error>');
                    continue;
                }
                break;
            }
            while (true) {
                $token = $this->getIo()->ask('请填写Packagist帐户API Token');
                if (empty($token)) {
                    $this->writeln('<error>帐户API Token不能为空</error>');
                    continue;
                }
                break;
            }

            file_put_contents($cacheFile, json_encode(['username' => $username, 'token' => $token]));
        }

        $cache = json_decode(file_get_contents($cacheFile), true);
        $composer = json_decode(file_get_contents($this->rootPath.'/composer.json'), true);
        $composerName = $composer['name'];

        $query = http_build_query(['username' => $cache['username'], 'apiToken' => $cache['token']]);
        $packagistDomain = static::$packagistDomain;
        $curl = curl_init("{$packagistDomain}/api/update-package?{$query}");
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode(['repository' => ['url' => "{$packagistDomain}/packages/{$composerName}"]]),
        ]);
        $res = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($statusCode === 200) {
            $this->writeln("<info>{$res}</info>");
        } else {
            $this->writeln("<error>{$res}</error>");
        }
    }

    /**
     * Get the symfony console instance.
     * 
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Get the symfony console style instance.
     * 
     * @return SymfonyStyle
     */
    public function getIo()
    {
        return $this->io;
    }

    /**
     * Output log and line feed.
     * 
     * @param string $msg
     */
    public function writeln($msg)
    {
        $this->log($msg, "writeln");
    }

    /**
     * Output log.
     * if there doesn`s have symfony output instance, then console log by echo.
     * 
     * @param string $msg
     * @return string $method
     */
    public function log($msg, $method = "write")
    {
        if (is_null($this->getOutput())) {
            echo $method == 'writeln' ? $msg . PHP_EOL : $msg;
        } else {
            call_user_func([$this->getOutput(), $method], $msg);
        }
    }
}
