<?php

declare(strict_types=1);

namespace WeDevelop\Menustructure\Model;

enum LinkType: string
{
    case Page = 'page';
    case Url = 'url';
    case File = 'file';
    case NoLink = 'no-link';
    case Breakpoint = 'breakpoint';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Page',
            self::Url => 'URL',
            self::File => 'File',
            self::NoLink => 'Not linked',
            self::Breakpoint => 'Breakpoint',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function dropdownSource(): array
    {
        $source = [];

        foreach (self::cases() as $case) {
            $source[$case->value] = $case->label();
        }

        return $source;
    }
}
