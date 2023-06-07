<?php

namespace SPF\IFace;

interface Protocol
{
    public function onStart($server);

    public function onConnect($server, $client_id, $tid);

    public function onReceive($server, $client_id, $tid, $data);

    public function onClose($server, $client_id, $tid_id);

    public function onShutdown($server);
}
