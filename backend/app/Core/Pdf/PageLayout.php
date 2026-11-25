<?php

namespace App\Core\Pdf;

/**
 * Layout of a letter in mm (Auftrag 39 F): margins of page 1 and of the following pages, and the address field in the
 * window of a C5 envelope. The defaults are the ones of the guide docs/briefpapier/ (values.json there; a test keeps
 * both the same).
 */
final readonly class PageLayout
{
    public const DEFAULTS = [
        'topFirst' => 100,
        'topFollowing' => 35,
        'bottom' => 25,
        'left' => 20,
        'right' => 20,
        'addressLeft' => 20,
        'addressTop' => 50,
        'addressWidth' => 75,
        'addressHeight' => 30,
    ];

    public function __construct(
        public float $topFirst = self::DEFAULTS['topFirst'],
        public float $topFollowing = self::DEFAULTS['topFollowing'],
        public float $bottom = self::DEFAULTS['bottom'],
        public float $left = self::DEFAULTS['left'],
        public float $right = self::DEFAULTS['right'],
        public float $addressLeft = self::DEFAULTS['addressLeft'],
        public float $addressTop = self::DEFAULTS['addressTop'],
        public float $addressWidth = self::DEFAULTS['addressWidth'],
        public float $addressHeight = self::DEFAULTS['addressHeight'],
    ) {}

    /**
     * @param  array<string, mixed>|null  $values  keys as in DEFAULTS; missing ones take the default
     */
    public static function fromArray(?array $values): self
    {
        $merged = array_merge(self::DEFAULTS, array_intersect_key(array_filter($values ?? [], 'is_numeric'), self::DEFAULTS));

        return new self(...array_map('floatval', $merged));
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
