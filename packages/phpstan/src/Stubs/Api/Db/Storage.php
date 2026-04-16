<?php

namespace Blockstudio\Api\Db;

enum Storage: string
{
    case Table = 'table';
    case Sqlite = 'sqlite';
    case Jsonc = 'jsonc';
    case Meta = 'meta';
    case PostType = 'post_type';
}
