<?php

namespace Statamic\Importer\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Importer\Support\SortByParent;
use Statamic\Importer\Tests\TestCase;

class SortByParentTest extends TestCase
{
    #[Test]
    #[DataProvider('provideData')]
    public function it_sorts_by_parent($input, $expected)
    {
        $this->assertEquals($expected, (new SortByParent)->sort($input));
    }

    public static function provideData(): array
    {
        return [
            'in acceptable order' => [
                // 1
                // |- 2
                // |  |- 3
                // |  |  |- 4
                // |  |- 5
                // |- 6
                [
                    ['id' => '2', 'parent' => '1'],
                    ['id' => '3', 'parent' => '2'],
                    ['id' => '4', 'parent' => '3'],
                    ['id' => '5', 'parent' => '2'],
                    ['id' => '6', 'parent' => '1'],
                ],
                [
                    ['id' => '2', 'parent' => '1'],
                    ['id' => '3', 'parent' => '2'],
                    ['id' => '4', 'parent' => '3'],
                    ['id' => '5', 'parent' => '2'],
                    ['id' => '6', 'parent' => '1'],
                ],
            ],

            'out of order' => [
                // 1
                // |- 2
                // |  |- 3
                // |  |  |- 4
                // |  |- 5
                // |- 6
                [
                    ['id' => '6', 'parent' => '1'],
                    ['id' => '4', 'parent' => '3'],
                    ['id' => '3', 'parent' => '2'],
                    ['id' => '5', 'parent' => '2'],
                    ['id' => '2', 'parent' => '1'],
                ],
                [
                    ['id' => '6', 'parent' => '1'],
                    ['id' => '2', 'parent' => '1'],
                    ['id' => '3', 'parent' => '2'],
                    ['id' => '4', 'parent' => '3'],
                    ['id' => '5', 'parent' => '2'],
                ],
            ],
        ];
    }
}
