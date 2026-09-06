<?php

namespace App\Enums;

enum BlockType: string
{
    case Text = 'text';
    case Gallery = 'gallery';
    case Files = 'files';
    case Links = 'links';
    case Code = 'code';
}
