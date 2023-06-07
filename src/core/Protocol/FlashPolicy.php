<?php
namespace SPF\Protocol;

use SPF;

class FlashPolicy extends Base implements SPF\IFace\Protocol
{
    public $default_port = 843;
    public $policy_file;
    public $policy_xml = '<cross-domain-policy>
<site-control permitted-cross-domain-policies="all"/>
<allow-access-from domain="*" to-ports="1000-9999" />
</cross-domain-policy>\0';

    public function setPolicyXml($filename)
    {
        $this->policy_file = $filename;
        $this->policy_xml = file_get_contents($filename);
    }

    public function onReceive($server, $client_id, $tid, $data)
    {
        echo $data;
        $this->server->send($client_id, $this->policy_xml);
        $this->server->close($client_id);
    }

    public function onStart($server)
    {
        $this->log(__CLASS__." running.");
    }

    public function onConnect($server, $client_id, $from_id)
    {
    }

    public function onClose($server, $client_id, $from_id)
    {
    }

    public function onShutdown($server)
    {
    }
}
