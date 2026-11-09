<?php

namespace App\Enums;

/**
 * Colour of a label: a hue of the Web Awesome palette. The frontend draws
 * it with the theme's colour tokens, so it fits light and dark mode.
 * The values match the GraphQL enum LabelColor.
 */
enum LabelColor: string
{
    case GRAY = 'gray';
    case RED = 'red';
    case ORANGE = 'orange';
    case YELLOW = 'yellow';
    case GREEN = 'green';
    case CYAN = 'cyan';
    case BLUE = 'blue';
    case INDIGO = 'indigo';
    case PURPLE = 'purple';
    case PINK = 'pink';
}
