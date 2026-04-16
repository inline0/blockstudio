<?php

namespace Blockstudio\Rpc;

enum Access: string
{
    case Authenticated = 'authenticated';
    case Session = 'session';
    case Open = 'open';
}
