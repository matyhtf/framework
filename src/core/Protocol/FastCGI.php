<?php
namespace SPF\Protocol;

use SPF;

class FastCGI extends WebServer
{
    protected $lowMark = 8; // initial value of the minimal amout of bytes in buffer
    protected $highMark = 0xFFFFFF; // initial value of the maximum amout of bytes in buffer
    public $timeout = 180;

    const HEADER_LENGTH = 8;

    const FCGI_BEGIN_REQUEST     = 1;
    const FCGI_ABORT_REQUEST     = 2;
    const FCGI_END_REQUEST       = 3;
    const FCGI_PARAMS            = 4;
    const FCGI_STDIN             = 5;
    const FCGI_STDOUT            = 6;
    const FCGI_STDERR            = 7;
    const FCGI_DATA              = 8;
    const FCGI_GET_VALUES        = 9;
    const FCGI_GET_VALUES_RESULT = 10;
    const FCGI_UNKNOWN_TYPE      = 11;

    const FCGI_RESPONDER  = 1;
    const FCGI_AUTHORIZER = 2;
    const FCGI_FILTER     = 3;

    protected static $roles = [
        self::FCGI_RESPONDER  => 'FCGI_RESPONDER',
        self::FCGI_AUTHORIZER => 'FCGI_AUTHORIZER',
        self::FCGI_FILTER     => 'FCGI_FILTER',
    ];

    const STATE_HEADER = 0;
    const STATE_BODY = 1;
    const STATE_PADDING = 2;

    function parseRecord($data)
    {
        $records = array();
        while (strlen($data))
        {
            if (strlen($data) < 8 )
            {
                /**
                 * We don't have a full header
                 */
                break;
            }
            $header = substr($data, 0, 8);
            $record = unpack('Cversion/Ctype/nrequestId/ncontentLength/CpaddingLength/Creserved', $header);
            //$record['requestId'] = $record['requestIdB1'] * 256 + $record['requestIdB0'];
           // $record['contentLength'] = $record['contentLengthB1'] * 256 + $record['contentLengthB0'];
            $recordlength = 8 + $record['contentLength'] + $record['paddingLength'];
            $record['contentData'] = substr($data, 8, $record['contentLength']);
            $record['paddingData'] = substr($data, 8 + $record['contentLength'], $record['paddingLength']);

            if (strlen($data) < $recordlength )
            {
                /**
                 * We don't have a full record.
                 */
                break;
            }
            $records[] = $record;
            $data = substr($data, $recordlength);
        }
        return array('records' => $records, 'remainder' => $data);
    }

    function xSendFile($req)
    {
        if ($this->config['sendfile'] and (!$this->config['sendfileonlybycommand'] or isset($req->server['USE_SENDFILE']))
            and !isset($req->server['DONT_USE_SENDFILE']))
        {
            $fn = tempnam($this->config['sendfiledir'], $this->config['sendfileprefix']);
            $req->sendfp = fopen($fn, 'wb');
            $req->header['X-Sendfile'] = $fn;
        }
    }

    function onReceive($serv, $fd, $tid, $data)
    {
        $result = $this->parseRecord($data);
        if (count($result['records']) == 0)
        {
            $this->log("Bad Request. data=".var_export($data, true)."\nresult: ".var_export($result, true));
            $this->server->close($fd);
            return;
        }
        foreach($result['records'] as $record)
        {
            $rid = $record['requestId'];
            $type = $record['type'];
            if ($type == self::FCGI_BEGIN_REQUEST)
            {
                $u = unpack('nrole/Cflags', $record['contentData']);
                $req = new SPF\Request();
                $req->fd = $fd;
                $req->attrs = new \StdClass;
                $req->attrs->role = self::$roles[$u['role']];
                $req->attrs->flags = $u['flags'];
                $req->id = $rid;
                $req->attrs->paramsDone = false;
                $req->attrs->inputDone = false;
                $req->attrs->chunked = false;
                $req->attrs->noHttpVer = true;
                $this->requests[$rid] = $req;
            }
            elseif (isset($this->requests[$rid]))
            {
                $req = $this->requests[$rid];
            }
            else {
                $this->log('Unexpected FastCGI-record #. Request ID: ' . $rid . '.');
                return;
            }

            if ($type === self::FCGI_ABORT_REQUEST)
            {
                $req->abort();
            }
            elseif ($type === self::FCGI_PARAMS)
            {
                if ($record['contentData'] === '')
                {
                    if (!isset($req->server['REQUEST_TIME']))
                    {
                        $req->server['REQUEST_TIME'] = time();
                    }
                    if (!isset($req->server['REQUEST_TIME_FLOAT']))
                    {
                        $req->server['REQUEST_TIME_FLOAT'] = microtime(true);
                    }
                    $req->attrs->paramsDone = true;
                }
                else
                {
                    $p = 0;
                    while ($p < $record['contentLength']) {
                        if (($namelen = ord($record['contentData']{$p})) < 128) {
                            ++$p;
                        }
                        else {
                            $u       = unpack('Nlen', chr(ord($record['contentData']{$p}) & 0x7f) . substr($record['contentData'], $p + 1, 3));
                            $namelen = $u['len'];
                            $p += 4;
                        }

                        if (($vlen = ord($record['contentData']{$p})) < 128) {
                            ++$p;
                        }
                        else {
                            $u    = unpack('Nlen', chr(ord($record['contentData']{$p}) & 0x7f) . substr($record['contentData'], $p + 1, 3));
                            $vlen = $u['len'];
                            $p += 4;
                        }

                        $req->server[substr($record['contentData'], $p, $namelen)] = substr($record['contentData'], $p + $namelen, $vlen);
                        $p += $namelen + $vlen;
                    }
                }
            }
            elseif ($type === self::FCGI_STDIN)
            {
                $this->log("FCGI_STDIN:".var_export($record, true));
            }
        }
        if ($req)
        {
            $response = $this->onRequest($req);
            $out = $response->getHeader(true);
            $out .= $response->body;
//            $this->log("response: ".$out);
            $this->response($req, $out);
            $this->endRequest($req, 0, -1);
        }
    }

    /**
     * Handles the output from downstream requests.
     * @param object Request.
     * @param string The output.
     * @return boolean Success
     */
    public function response($req, $out)
    {
        $cs = $chunksize = 8192;
        if (strlen($out) > $cs)
        {
            while (($ol = strlen($out)) > 0)
            {
                $l = min($cs, $ol);
                if ($this->sendChunk($req, substr($out, 0, $l)) === false)
                {
                    $this->log("send response failed.");
                    return false;
                }
                $out = substr($out, $l);
            }
        }
        elseif ($this->sendChunk($req, $out) === false)
        {
            $this->log("send response failed.");
            return false;
        }
        return true;
    }

    /**
     * Sends a chunk
     * @param $req
     * @param $chunk
     * @return bool
     */
    public function sendChunk($req, $chunk)
    {
        return $this->server->send($req->fd,
            "\x01" // protocol version
            . "\x06" // record type (STDOUT)
            . pack('nn', $req->id, strlen($chunk)) // id, content length
            . "\x00" // padding length
            . "\x00" // reserved
        ) && $this->server->send($req->fd, $chunk); // content
    }

    /**
     * Handles the output from downstream requests.
     * @param $req
     * @param $appStatus
     * @param $protoStatus
     * @return void
     */
    public function endRequest($req, $appStatus = 0, $protoStatus = 0)
    {
        $c = pack('NC', $appStatus, $protoStatus) // app status, protocol status
            . "\x00\x00\x00";

        $this->server->send($req->fd,
            "\x01" // protocol version
            . "\x03" // record type (END_REQUEST)
            . pack('nn', $req->id, strlen($c)) // id, content length
            . "\x00" // padding length
            . "\x00" // reserved
            . $c // content
        );

        if ($protoStatus === -1) {
            $this->server->close($req->fd);
        }
//        elseif (!$this->pool->config->keepalive->value) {
//            $this->finish();
//        }
    }

    public function onClose($serv, $fd, $req)
    {
        unset($this->requests[$fd]);
    }
}
