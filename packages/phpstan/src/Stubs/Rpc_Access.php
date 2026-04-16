<?php

namespace Blockstudio;

enum Rpc_Access: string
{
    case Authenticated = 'authenticated';
    case Session = 'session';
    case Open = 'open';
}
