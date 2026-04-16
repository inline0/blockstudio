<?php

namespace Blockstudio\Api\Rpc;

enum Access: string
{
    case Authenticated = 'authenticated';
    case Session = 'session';
    case Open = 'open';
}
