<?php
namespace SPF\Protocol;
use SPF;

class FtpServer extends Base
{
    const EOF = "\r\n";
    static $software = "swoole-ftp-server";

    /**
     * @var \swoole_server
     */
    protected $serv;
    protected $connections = array();

    public $users = array();

    function send($socket, $msg)
    {
        $msg = strtr($msg, array("\n" => "", "\0" => "", "\r" => ""));
        echo "[-->]\t" . $msg . "\n";
        return $this->serv->send($socket, $msg . self::EOF);
    }

    function isIPAddress($ip)
    {
        if (!is_numeric($ip[0]) || $ip[0] < 1 || $ip[0] > 254) {
            return false;
        } elseif (!is_numeric($ip[1]) || $ip[1] < 0 || $ip[1] > 254) {
            return false;
        } elseif (!is_numeric($ip[2]) || $ip[2] < 0 || $ip[2] > 254) {
            return false;
        } elseif (!is_numeric($ip[3]) || $ip[3] < 1 || $ip[3] > 254) {
            return false;
        } elseif (!is_numeric($ip[4]) || $ip[4] < 1 || $ip[4] > 500) {
            return false;
        } elseif (!is_numeric($ip[5]) || $ip[5] < 1 || $ip[5] > 500) {
            return false;
        } else {
            return true;
        }
    }

    function onStart($serv)
    {
        $this->serv = $serv;
        //SPF\Console::changeUser('www-data');
    }

    function onConnect($serv, $fd, $from_id)
    {
        $this->connections[$fd] = array();
        $this->send($fd, "220---------- Welcome to " . self::$software . " ----------");
        $this->send($fd, "220-Local time is now " . date("H:i"));
        $this->send($fd, "220 This is a private system - No anonymous login");
    }

    /**
     * 获取当前目录
     * @param $fd
     * @param $cmd
     */
    function cmd_PWD($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $this->send($fd, "257 \"" . $this->getUserDir($user) . "\" is your current location");
    }

    /**
     * 更改当前目录
     * @param $fd
     * @param $cmd
     */
    function cmd_CWD($fd, $cmd)
    {
        $user = $this->getUser($fd);
        if (($dir = $this->setUserDir($user, $cmd[1])) != false)
        {
            $this->send($fd, "250 OK. Current directory is " . $dir);
        }
        else
        {
            $this->send($fd, "550 Can't change directory to " . $cmd[1] . ": No such file or directory");
        }
    }

    /**
     * 上传文件
     * @param $fd
     * @param $cmd
     */
    function cmd_STOR($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        $file = $this->fillDirName($user, $cmd[1]);
        $this->debug("PUT: $file");
        $fp = fopen($file, "w");
        if (!$fp)
        {
            $this->send($fd, "553 Can't open that file: Permission denied");
        }
        else
        {
            $this->send($fd, "150 Connecting to client");
            while (!feof($ftpsock))
            {
                $cont = fread($ftpsock, 8192);
                if (!$cont) break;
                if (!fwrite($fp, $cont)) break;
            }
            if (fclose($fp) and $this->closeUserSock($user))
            {
                $this->send($fd, "226 File successfully transferred");
            }
            else
            {
                $this->send($fd, "550 Error during file-transfer");
            }
        }
    }

    /**
     * 删除目录
     * @param $fd
     * @param $cmd
     */
    function cmd_RMD($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $dir = $this->fillDirName($user, $cmd[1]);
        $this->debug("RMDIR: $dir");
        if (is_dir(dirname($dir)) and is_dir($dir))
        {
            if (count(glob($dir . "/*")))
            {
                $this->send($fd, "550 Can't remove directory: Directory not empty");
            }
            elseif (rmdir($dir))
            {
                $this->send($fd, "250 The directory was successfully removed");
            }
            else
            {
                $this->send($fd, "550 Can't remove directory: Operation not permitted");
            }
        }
        elseif (is_dir(dirname($dir)) and file_exists($dir))
        {
            $this->send($fd, "550 Can't remove directory: Not a directory");
        }
        else
        {
            $this->send($fd, "550 Can't create directory: No such file or directory");
        }
    }

    /**
     * 删除文件
     * @param $fd
     * @param $cmd
     */
    function cmd_DELE($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $file = $this->fillDirName($user, $cmd[1]);
        $this->debug("DEL: $file");
        if (!file_exists($file))
        {
            $this->send($fd, "550 Could not delete " . $cmd[1] . ": No such file or directory");
        }
        elseif (unlink($file))
        {
            $this->send($fd, "250 Deleted " . $cmd[1]);
        }
        else
        {
            $this->send($fd, "550 Could not delete " . $cmd[1] . ": Permission denied");
        }
    }

    /**
     * 得到服务器类型
     * @param $fd
     * @param $cmd
     */
    function cmd_SYST($fd, $cmd)
    {
        $this->send($fd, "215 UNIX Type: L8");
    }

    /**
     * 创建目录
     * @param $fd
     * @param $cmd
     */
    function cmd_MKD($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $path = $this->getAbsDir($user).$cmd[1];

        if (!is_dir(dirname($path)))
        {
            $this->send($fd, "550 Can't create directory: No such file or directory");
        }
        elseif(file_exists($path))
        {
            $this->send($fd, "550 Can't create directory: File exists");
        }
        else
        {
            if (mkdir($path))
            {
                $this->send($fd, "257 \"" . $cmd[1] . "\" : The directory was successfully created");
            }
            else
            {
                $this->send($fd, "550 Can't create directory: Permission denied");
            }
        }
    }

    /**
     * 文件重命名,目标文件
     * @param $fd
     * @param $cmd
     */
    function cmd_RNTO($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $old_file = $this->users[$user]['rename'];
        $new_file = $this->fillDirName($user, $cmd[1]);
        $this->debug("RENAME: $old_file to $new_file");
        if (empty($old_file) or !is_dir(dirname($new_file)))
        {
            $this->send($fd, "451 Rename/move failure: No such file or directory");
        }
        elseif (rename($old_file, $new_file))
        {
            $this->send($fd, "250 File successfully renamed or moved");
        }
        else
        {
            $this->send($fd, "451 Rename/move failure: Operation not permitted");
        }
        unset($this->users[$user]['rename']);
    }

    /**
     * 文件重命名,源文件
     * @param $fd
     * @param $cmd
     */
    function cmd_RNFR($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $file = $this->fillDirName($user, $cmd[1]);
        if (!file_exists($file))
        {
            $this->send($fd, "550 Sorry, but that file doesn't exist");
        }
        else
        {
            $this->users[$user]['rename'] = $file;
            $this->send($fd, "350 RNFR accepted - file exists, ready for destination");
        }
    }

    /**
     * 权限控制
     * @param $fd
     * @param $cmd
     */
    function cmd_SITE($fd, $cmd)
    {
        if (substr($cmd[1], 0, 6) == "CHMOD ")
        {
            $user = $this->getUser($fd);
            $chmod = explode(" ", $cmd[1], 3);
            $file = $this->fillDirName($user, $chmod[2]);
            $this->debug("CHMOD: $file to {$chmod[1]}");
            if (chmod($file, octdec($chmod[1])))
            {
                $this->send($fd, "200 Permissions changed on {$chmod[2]}");
            }
            else
            {
                $this->send($fd, "550 Could not change perms on " . $chmod[2] . ": Permission denied");
            }
        }
        else
        {
            $this->send($fd, "500 Unknown Command");
        }
    }

    /**
     * 登录用户名
     * @param $fd
     * @param $cmd
     */
    function cmd_USER($fd, $cmd)
    {
        if (preg_match("/^([a-z0-9]+)$/", $cmd[1]))
        {
            $user = $cmd[1];
            $this->connections[$fd]['user'] = $user;
            $this->users[$user]['fd'] = $fd;
            $this->send($fd, "331 User $user OK. Password required");
        }
        else
        {
            $this->send($fd, "530 Login authentication failed");
        }
    }

    /**
     * 登录密码
     * @param $fd
     * @param $cmd
     */
    function cmd_PASS($fd, $cmd)
    {
        $user = $this->connections[$fd]['user'];
        $pass = $cmd[1];
        if (isset($this->users[$user]) and $this->users[$user]['password'] == $pass)
        {
            if ($this->users[$user]['chroot'])
            {
                $dir = "/";
            }
            else
            {
                $dir = $this->users[$user]['home'];
            }
            $this->users[$user]['pwd'] = $dir;
            $this->send($fd, "230 OK. Current restricted directory is " . $dir);
            $this->connections[$fd]['login'] = true;
            //send($socket,"230 0 Kbytes used (0%) - authorized: 102400 Kb\r\n");
        }
        else
        {
            $this->send($fd, "530 Login authentication failed");
        }
    }

    /**
     * 下载文件
     * @param $fd
     * @param $cmd
     */
    function cmd_RETR($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        if (($file = $this->getFile($user, $cmd[1])) != false)
        {
            $this->send($fd, "150 Connecting to client");
            if ($fp = fopen($file, "r"))
            {
                while (!feof($fp))
                {
                    $cont = fread($fp, 1024);
                    if (!fwrite($ftpsock, $cont)) break;
                }
                if (fclose($fp) and $this->closeUserSock($user))
                {
                    $this->send($fd, "226 File successfully transferred");
                }
                else
                {
                    $this->send($fd, "550 Error during file-transfer");
                }
            }
            else
            {
                $this->send($fd, "550 Can't open " . $cmd[1] . ": Permission denied");
            }
        }
        else
        {
            $this->send($fd, "550 Can't open " . $cmd[1] . ": No such file or directory");
        }
    }

    /**
     * 退出服务器
     * @param $fd
     * @param $cmd
     */
    function cmd_QUIT($fd, $cmd)
    {
        $this->send($fd,"221 Goodbye.");
        unset($this->connections[$fd]);
    }

    /**
     * 更改传输类型
     * @param $fd
     * @param $cmd
     */
    function cmd_TYPE($fd, $cmd)
    {
        switch ($cmd[1])
        {
            case "A":
                $type = "ASCII";
                break;
            case "I":
                $type = "8-bit binary";
                break;
        }
        $this->send($fd, "200 TYPE is now " . $type);
    }

    /**
     * 遍历目录
     * @param $fd
     * @param $cmd
     */
    function cmd_LIST($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        if (!$ftpsock)
        {
            $this->send($fd, "501 Connection Error");
            return;
        }
        $this->send($fd, "150 Opening ASCII mode data connection for file list");
        $path = $this->getAbsDir($user);
        if (isset($cmd[1]) and preg_match("/\-(.*)a/", $cmd[1]))
        {
            $showHidden = true;
        }
        else
        {
            $showHidden = false;
        }
        $filelist = $this->getFileList($path, $showHidden);
        fwrite($ftpsock, $filelist);
        $this->closeUserSock($user);
        $this->send($fd, "226 Transfer complete.");
    }

    /**
     * 建立数据传输通道
     * @param $fd
     * @param $cmd
     */
    function cmd_PORT($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $port = explode(",", $cmd[1]);
        if (count($port) != 6)
        {
            $this->send($fd, "501 Syntax error in IP address");
        }
        else
        {
            if (!$this->isIPAddress($port))
            {
                $this->send($fd, "501 Syntax error in IP address");
                return;
            }
            $ip = $port[0] . "." . $port[1] . "." . $port[2] . "." . $port[3];
            $port = hexdec(dechex($port[4]) . dechex($port[5]));
            if ($port < 1024)
            {
                $this->send($fd, "501 Sorry, but I won't connect to ports < 1024");
            }
            elseif ($port > 65000)
            {
                $this->send($fd, "501 Sorry, but I won't connect to ports > 65000");
            }
            else
            {
                $ftpsock = fsockopen($ip, $port);
                if ($ftpsock)
                {
                    $this->users[$user]['sock'] = $ftpsock;
                    $this->users[$user]['pasv'] = false;
                    $this->send($fd, "200 PORT command successful");
                }
                else
                {
                    $this->send($fd, "501 Connection failed");
                }
            }
        }
    }

    function onReceive($serv, $fd, $tid, $recv_data)
    {
        $read = trim($recv_data);
        echo "[<--]\t" . $read . "\n";
        $cmd = explode(" ", $read, 2);

        $func = 'cmd_'.$cmd[0];
        if (!method_exists($this, $func))
        {
            $this->send($fd, "500 Unknown Command");
            return;
        }
        if (empty($this->connections[$fd]['login']))
        {
            switch($cmd[0])
            {
                case 'TYPE':
                case 'USER':
                case 'PASS':
                case 'QUIT':
                    break;
                default:
                    $this->send($fd,"530 You aren't logged in");
                    return;
            }
        }
        $this->$func($fd, $cmd);
    }

    function debug($msg)
    {
        echo "[DD]\t".$msg."\n";
    }

    function getUser($fd)
    {
        return $this->connections[$fd]['user'];
    }

    /**
     * 关闭数据传输socket
     * @param $user
     * @return bool
     */
    function closeUserSock($user)
    {
        fclose($this->users[$user]['sock']);
        $this->users[$user]['sock'] = 0;
        return true;
    }

    /**
     * @param $user
     * @return resource
     */
    function getUserSock($user)
    {
        //被动模式
        if ($this->users[$user]['pasv'] == true)
        {
            if (empty($this->users[$user]['sock']))
            {
                $sock = stream_socket_accept($this->users[$user]['serv_sock'], 1);

                if ($sock)
                {
                    $peer = stream_socket_get_name($sock, true);
                    $this->debug("Accept: success client is $peer.");
                    $this->users[$user]['sock'] = $sock;
                    //关闭server socket
                    fclose($this->users[$user]['serv_sock']);
                }
                else
                {
                    $this->debug("Accept: failed.");
                    return false;
                }
            }
        }
        return $this->users[$user]['sock'];
    }

    function getFile($user, $file)
    {
        $file = $this->fillDirName($user, $file);
        $this->debug("GET: $file");

        if (is_file($file))
        {
            return realpath($file);
        }
        else
        {
            return false;
        }
    }

    function cmd_CDUP($fd, $cmd)
    {
        $cmd[1] = '..';
        $this->cmd_CWD($fd, $cmd);
    }

    function cmd_EPSV($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $sock = stream_socket_server('tcp://0.0.0.0:0',$errno , $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if ($sock)
        {
            $addr = stream_socket_get_name($sock, false);
            list($ip, $port) = explode(':', $addr);
            $this->send($fd, "229 Entering Extended Passive Mode (|||$port|)");
            $this->users[$user]['serv_sock'] = $sock;
            $this->users[$user]['pasv'] = true;
        }
        else
        {
            $this->send($fd, "500 failed to create data socket.");
        }
    }

    function cmd_PASV($fd, $cmd)
    {
        $user = $this->getUser($fd);
        $sock = stream_socket_server('tcp://0.0.0.0:0', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if ($sock)
        {
            $addr = stream_socket_get_name($sock, false);
            list($ip, $port) = explode(':', $addr);
            $this->debug("ServerSock: $ip:$port");
            $ip = str_replace('.', ',', $ip);
            $this->send($fd, "227 Entering Passive Mode ({$ip},".(intval($port) >> 8 & 0xff).",".(intval($port) & 0xff).").");
            $this->users[$user]['serv_sock'] = $sock;
            $this->users[$user]['pasv'] = true;
        }
        else
        {
            $this->send($fd, "500 failed to create data socket.");
        }
    }

    /**
     * 遍历目录
     * @param $rdir
     * @param $showHidden
     * @return string
     */
    function getFileList($rdir, $showHidden = false)
    {
        $filelist = '';
        if ($handle = opendir($rdir))
        {
            while (false !== ($file = readdir($handle)))
            {
                if ($file == '.' or $file == '..')
                {
                    continue;
                }

                if ($file{0} == "." and !$showHidden)
                {
                    continue;
                }

                $stats = stat($rdir . "/" . $file);
                if (is_dir($rdir . "/" . $file)) $mode = "d"; else $mode = "-";
                $moded = sprintf("%o", ($stats['mode'] & 000777));
                $mode1 = substr($moded, 0, 1);
                $mode2 = substr($moded, 1, 1);
                $mode3 = substr($moded, 2, 1);
                switch ($mode1) {
                    case "0":
                        $mode .= "---";
                        break;
                    case "1":
                        $mode .= "--x";
                        break;
                    case "2":
                        $mode .= "-w-";
                        break;
                    case "3":
                        $mode .= "-wx";
                        break;
                    case "4":
                        $mode .= "r--";
                        break;
                    case "5":
                        $mode .= "r-x";
                        break;
                    case "6":
                        $mode .= "rw-";
                        break;
                    case "7":
                        $mode .= "rwx";
                        break;
                }
                switch ($mode2) {
                    case "0":
                        $mode .= "---";
                        break;
                    case "1":
                        $mode .= "--x";
                        break;
                    case "2":
                        $mode .= "-w-";
                        break;
                    case "3":
                        $mode .= "-wx";
                        break;
                    case "4":
                        $mode .= "r--";
                        break;
                    case "5":
                        $mode .= "r-x";
                        break;
                    case "6":
                        $mode .= "rw-";
                        break;
                    case "7":
                        $mode .= "rwx";
                        break;
                }
                switch ($mode3) {
                    case "0":
                        $mode .= "---";
                        break;
                    case "1":
                        $mode .= "--x";
                        break;
                    case "2":
                        $mode .= "-w-";
                        break;
                    case "3":
                        $mode .= "-wx";
                        break;
                    case "4":
                        $mode .= "r--";
                        break;
                    case "5":
                        $mode .= "r-x";
                        break;
                    case "6":
                        $mode .= "rw-";
                        break;
                    case "7":
                        $mode .= "rwx";
                        break;
                }
                $uidfill = "";
                for ($i = strlen($stats['uid']); $i < 5; $i++) $uidfill .= " ";
                $gidfill = "";
                for ($i = strlen($stats['gid']); $i < 5; $i++) $gidfill .= " ";
                $sizefill = "";
                for ($i = strlen($stats['size']); $i < 11; $i++) $sizefill .= " ";
                $nlinkfill = "";
                for ($i = strlen($stats['nlink']); $i < 5; $i++) $nlinkfill .= " ";
                $mtime = date("M d H:i", $stats['mtime']);
                $filelist .= $mode . $nlinkfill . $stats['nlink'] . " " . $stats['uid'] . $uidfill . $stats['gid'] . $gidfill . $sizefill . $stats['size'] . " " . $mtime . " " . $file . "\r\n";
            }
            closedir($handle);
        }
        return $filelist;
    }

    /**
     * 设置用户当前的路径
     * @param $user
     * @param $pwd
     */
    function setUserDir($user, $cdir)
    {
        $old_dir = $this->users[$user]['pwd'];
        if ($old_dir == $cdir)
        {
            return $cdir;
        }
        
        if($cdir[0] != '/')
        {
            $cdir = $old_dir.'/'.$cdir ;
        }
        
        $this->debug("CHDIR: $old_dir -> $cdir");
        $this->users[$user]['pwd'] = $cdir;
        $abs_dir = realpath($this->getAbsDir($user));
        if (!$abs_dir)
        {
            $this->users[$user]['pwd'] = $old_dir;
            return false;
        }
        $this->users[$user]['pwd'] = '/'.substr($abs_dir, strlen($this->users[$user]['home']));
        return $this->users[$user]['pwd'];
    }

    /**
     * 补齐路径
     * @param $user
     * @param $file
     * @return string
     */
    function fillDirName($user, $file)
    {
        //发过来的文件名不带路径需要补齐
        if (substr($file, 0, 1) != "/")
        {
            $file = $this->getUserDir($user) . "/" . $file;
        }
        if ($this->users[$user]['chroot'])
        {
            $file = $this->users[$user]['home'].$file;
        }
        return $file;
    }

    function getUserDir($user)
    {
        return $this->users[$user]['pwd'];
    }

    /**
     * 获取用户的当前文件系统绝对路径，非chroot路径
     * @param $user
     * @return string
     */
    function getAbsDir($user)
    {
        if (!$this->users[$user]['chroot'])
        {
            $rdir = $this->users[$user]['pwd'];
        }
        else
        {
            $rdir = $this->users[$user]['home'].$this->users[$user]['pwd'];
        }
        return $rdir;
    }
}
