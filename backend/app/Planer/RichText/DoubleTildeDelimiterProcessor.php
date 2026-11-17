<?php

namespace App\Planer\RichText;

use League\CommonMark\Delimiter\DelimiterInterface;
use League\CommonMark\Delimiter\Processor\DelimiterProcessorInterface;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Node\Inline\AbstractStringContainer;

/**
 * Strikethrough only for `~~text~~`. The processor of league/commonmark also
 * pairs single tildes (`~a ... b~`), which would tear apart bold and italic
 * around them; here a single tilde is just a character.
 */
final class DoubleTildeDelimiterProcessor implements DelimiterProcessorInterface
{
    public function getOpeningCharacter(): string
    {
        return '~';
    }

    public function getClosingCharacter(): string
    {
        return '~';
    }

    public function getMinLength(): int
    {
        return 2;
    }

    public function getDelimiterUse(DelimiterInterface $opener, DelimiterInterface $closer): int
    {
        return $opener->getLength() === 2 && $closer->getLength() === 2 ? 2 : 0;
    }

    public function process(AbstractStringContainer $opener, AbstractStringContainer $closer, int $delimiterUse): void
    {
        $strikethrough = new Strikethrough('~~');

        for ($node = $opener->next(); $node !== null && $node !== $closer;) {
            $next = $node->next();
            $strikethrough->appendChild($node);
            $node = $next;
        }

        $opener->insertAfter($strikethrough);
    }
}
