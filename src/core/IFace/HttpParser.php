<?php
namespace SPF\IFace;

interface HttpParser
{
    public function parseHeader($header);
    public function parseBody($request);
    public function parseCookie($request);
}
