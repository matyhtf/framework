<?php

namespace SPF\IFace;

interface Protocol
{
    function onStart($server);

    function onConnect($server, $client_id, $tid);

    function onReceive($server, $client_id, $tid, $data);

    function onClose($server, $client_id, $tid_id);

    function onShutdown($server);
}
