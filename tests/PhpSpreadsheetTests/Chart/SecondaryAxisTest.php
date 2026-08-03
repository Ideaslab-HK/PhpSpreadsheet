<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheetTests\Chart;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PHPUnit\Framework\TestCase;

class SecondaryAxisTest extends TestCase
{
    public function testSecondaryAxisDefaultsToFalseAndIsFluent(): void
    {
        [, $secondarySeries] = $this->createChartSeries();

        self::assertFalse($secondarySeries->isSecondaryAxis());
        self::assertSame($secondarySeries, $secondarySeries->setSecondaryAxis());
        self::assertTrue($secondarySeries->isSecondaryAxis());
        self::assertSame($secondarySeries, $secondarySeries->setSecondaryAxis(false));
        self::assertFalse($secondarySeries->isSecondaryAxis());
        self::assertTrue($secondarySeries->areSecondaryAxisLabelsVisible());
        self::assertSame($secondarySeries, $secondarySeries->setSecondaryAxisLabelsVisible(false));
        self::assertFalse($secondarySeries->areSecondaryAxisLabelsVisible());
    }

    public function testWriterCreatesSecondaryAxes(): void
    {
        $spreadsheet = $this->createSpreadsheetWithSecondaryAxisChart();
        $chart = $spreadsheet->getActiveSheet()->getChartCollection()[0];
        $chart->getPlotArea()?->setGapWidth(0);
        $chart->getPlotArea()?->getPlotGroupByIndex(1)->setSecondaryAxisLabelsVisible(false);
        $writer = new XlsxWriter($spreadsheet);
        $chartWriter = new XlsxWriter\Chart($writer);
        $xml = $chartWriter->writeChart($chart);
        $spreadsheet->disconnectWorksheets();

        self::assertSame(2, substr_count($xml, '<c:catAx>'));
        self::assertSame(2, substr_count($xml, '<c:valAx>'));
        self::assertSame(1, substr_count($xml, '<c:axPos val="t"/>'));
        self::assertSame(1, substr_count($xml, '<c:axPos val="r"/>'));
        self::assertSame(1, substr_count($xml, '<c:gapWidth val="0"/>'));
        self::assertSame(2, substr_count($xml, '<c:tickLblPos val="none"/>'));
        self::assertSame(2, substr_count($xml, '<c:tickLblPos val="nextTo"/>'));
        self::assertStringContainsString('<c:axId val="110450432"/>', $xml);
        self::assertStringContainsString('<c:axId val="110456320"/>', $xml);
    }

    public function testSecondaryAxisSurvivesXlsxRoundTrip(): void
    {
        $spreadsheet = $this->createSpreadsheetWithSecondaryAxisChart();
        $spreadsheet->getActiveSheet()->getChartCollection()[0]
            ->getPlotArea()?->getPlotGroupByIndex(1)->setSecondaryAxisLabelsVisible(false);
        $filename = tempnam(sys_get_temp_dir(), 'phpspreadsheet-secondary-axis-');
        self::assertNotFalse($filename);

        try {
            $writer = new XlsxWriter($spreadsheet);
            $writer->setIncludeCharts(true);
            $writer->save($filename);
            $spreadsheet->disconnectWorksheets();

            $reader = new XlsxReader();
            $reader->setIncludeCharts(true);
            $spreadsheet = $reader->load($filename);
            $plotGroups = $spreadsheet->getActiveSheet()->getChartCollection()[0]->getPlotArea()?->getPlotGroup();

            self::assertNotNull($plotGroups);
            self::assertCount(2, $plotGroups);
            self::assertFalse($plotGroups[0]->isSecondaryAxis());
            self::assertTrue($plotGroups[1]->isSecondaryAxis());
            self::assertFalse($plotGroups[1]->areSecondaryAxisLabelsVisible());
        } finally {
            $spreadsheet->disconnectWorksheets();
            unlink($filename);
        }
    }

    public function testSameChartTypeUsesSeparatePlotGroups(): void
    {
        $spreadsheet = $this->createSpreadsheetWithSecondaryAxisChart();
        $chart = $spreadsheet->getActiveSheet()->getChartCollection()[0];
        $plotGroups = $chart->getPlotArea()?->getPlotGroup();
        self::assertNotNull($plotGroups);
        $plotGroups[1]->setPlotType(DataSeries::TYPE_LINECHART);

        $writer = new XlsxWriter($spreadsheet);
        $chartWriter = new XlsxWriter\Chart($writer);
        $xml = $chartWriter->writeChart($chart);
        $spreadsheet->disconnectWorksheets();

        self::assertSame(2, substr_count($xml, '<c:lineChart>'));
        self::assertSame(2, substr_count($xml, '<c:catAx>'));
        self::assertSame(2, substr_count($xml, '<c:valAx>'));
    }

    private function createSpreadsheetWithSecondaryAxisChart(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->fromArray([
            ['Category', 'Primary', 'Secondary'],
            ['A', 10, 100],
            ['B', 20, 200],
        ]);
        [$primarySeries, $secondarySeries] = $this->createChartSeries();
        $secondarySeries->setSecondaryAxis();

        $chart = new Chart('dual-axis', null, null, new PlotArea(null, [$primarySeries, $secondarySeries]));
        $chart->setTopLeftPosition('E2');
        $chart->setBottomRightPosition('M20');
        $worksheet->addChart($chart);

        return $spreadsheet;
    }

    /** @return array{DataSeries, DataSeries} */
    private function createChartSeries(): array
    {
        $categories = [new DataSeriesValues(
            DataSeriesValues::DATASERIES_TYPE_STRING,
            'Worksheet!$A$2:$A$3',
            null,
            2
        )];
        $primarySeries = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            [0],
            [],
            $categories,
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$B$2:$B$3', null, 2)]
        );
        $secondarySeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [1],
            [],
            $categories,
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Worksheet!$C$2:$C$3', null, 2)]
        );

        return [$primarySeries, $secondarySeries];
    }
}