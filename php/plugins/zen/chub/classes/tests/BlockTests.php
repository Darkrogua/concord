<?php namespace Zen\Chub\Classes\Tests;

use ArrayObject;
use Zen\Chub\Classes\System\BlockPartial;
use Zen\Chub\Classes\System\BlockPaths;

class BlockPathsTests
{
    public static function make(): self
    {
        return new self();
    }

    public function testDomId(): array
    {
        $paths = BlockPaths::make();
        $cases = [
            ['header', 'header'],
            ['svg_ships/map_desktop', 'svg_ships--map_desktop'],
            ['alerts/alert', 'alerts--alert'],
            ['tabs/tabs', 'tabs--tabs'],
        ];

        foreach ($cases as [$code, $expected]) {
            if ($paths->domId($code) !== $expected) {
                return ['ok' => false, 'error' => "domId({$code}) expected {$expected}"];
            }
        }

        if ($paths->domIdFromPartialKey('header/header') !== 'header') {
            return ['ok' => false, 'error' => 'domIdFromPartialKey header/header'];
        }

        return ['ok' => true];
    }
}

class BlockPartialTests
{
    public static function make(): self
    {
        return new self();
    }

    public function testMountWithExplicitData(): array
    {
        $partial = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);
        $partial['data'] = ['title' => 'Test title'];

        BlockPartial::mount($partial, 'alerts/alert');

        if (($partial['block_dom_id'] ?? '') !== 'alerts--alert') {
            return ['ok' => false, 'error' => 'block_dom_id missing'];
        }

        if (($partial['title'] ?? '') !== 'Test title') {
            return ['ok' => false, 'error' => 'title not unpacked from data'];
        }

        return ['ok' => true];
    }

    public function testMountPreviewLoadsDemo(): array
    {
        $partial = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);
        $partial['_store_book'] = true;
        $partial['demo'] = 'warning';

        BlockPartial::mount($partial, 'alerts/alert');

        if (($partial['block_dom_id'] ?? '') !== 'alerts--alert') {
            return ['ok' => false, 'error' => 'block_dom_id missing in preview'];
        }

        if (!is_array($partial['data'] ?? null) || $partial['data'] === []) {
            return ['ok' => false, 'error' => 'preview data empty'];
        }

        return ['ok' => true];
    }

    public function testMountWithoutDataLoadsDefaultDemo(): array
    {
        $partial = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);

        BlockPartial::mount($partial, 'favicons/favicons');

        if (($partial['manifest_href'] ?? '') === '') {
            return ['ok' => false, 'error' => 'manifest_href not loaded from default demo JSON'];
        }

        return ['ok' => true];
    }
}
