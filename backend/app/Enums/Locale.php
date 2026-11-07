<?php

namespace App\Enums;

/**
 * Language of a project; decides the language of its notification mails.
 * Names and values match the GraphQL enum Locale.
 */
enum Locale: string
{
    case DE = 'de';
    case EN = 'en';
}
