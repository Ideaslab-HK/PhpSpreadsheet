<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheetTests\Chart;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataSeriesShowValTest extends TestCase
{
    public function testShowValDefaultsToNullAndIsFluent(): void
    {
        $series = new DataSeries(plotValues: [new DataSeriesValues()]);

        self::assertNull($series->getShowVal());
        self::assertSame($series, $series->setShowVal(true));
        self::assertTrue($series->getShowVal());
        self::assertSame($series, $series->setShowVal(null));
        self::assertNull($series->getShowVal());
    }

    #[DataProvider('showValProvider')]
    public function testShowValOverridesLayout(bool $layoutShowVal, bool $seriesShowVal): void
    {
        $spreadsheet = new Spreadsheet();
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, null, null, 2, [1, 2]),
        ];
        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            [],
            [],
            $values
        );
        $series->setShowVal($seriesShowVal);

        $layout = new Layout();
        $layout->setShowVal($layoutShowVal);
        $chart = new Chart('chart', null, null, new PlotArea($layout, [$series]));
        $spreadsheet->getActiveSheet()->addChart($chart);

        $writer = new Xlsx($spreadsheet);
        $chartXml = (new Xlsx\Chart($writer))->writeChart($chart);

        self::assertStringContainsString(
            sprintf('<c:ser><c:idx val="0"/><c:order val="0"/><c:dLbls><c:showVal val="%d"/></c:dLbls>', (int) $seriesShowVal),
            $chartXml
        );
        self::assertSame(1, substr_count($chartXml, sprintf('<c:showVal val="%d"/>', (int) $layoutShowVal)));
    }

    /** @return array<string, array{bool, bool}> */
    public static function showValProvider(): array
    {
        return [
            'series false overrides layout true' => [true, false],
            'series true overrides layout false' => [false, true],
        ];
    }
}