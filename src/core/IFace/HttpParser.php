<?php
namespace SPF\IFace;

interface HttpParser
{
    function parseHeader($header);
    function parseBody($request);
    function parseCookie($request);
}