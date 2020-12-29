<?php

namespace Enlightn\Enlightn\Console;

use Enlightn\Enlightn\Enlightn;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\TableStyle;

class EnlightnCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enlightn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enlightn your application!';

    /**
     * The number of analyzers to run.
     *
     * @var int
     */
    protected $totalAnalyzers;

    /**
     * The number of analyzers that have completed their analysis.
     *
     * @var int
     */
    protected $countAnalyzers;

    /**
     * The category of the analyzers currently being run.
     *
     * @var string|null
     */
    protected $category = null;

    /**
     * The final result of the analysis.
     *
     * @var array
     */
    protected $result = [];

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \ReflectionException|\Illuminate\Contracts\Container\BindingResolutionException
     */
    public function handle()
    {
        $this->setColors();
        $this->line(require __DIR__.DIRECTORY_SEPARATOR.'logo.php');
        $this->output->newLine();
        $this->line('Please wait while Enlightn scans your code base...');

        Enlightn::register();

        $this->totalAnalyzers = Enlightn::totalAnalyzers();
        $this->countAnalyzers = 1;
        $this->initializeResult();

        Enlightn::using([$this, 'printAnalyzerOutput']);
        Enlightn::run($this->laravel);

        $this->printReportCard();

        return 0;
    }

    /**
     * @param array $info
     *
     * @return void
     */
    public function printAnalyzerOutput(array $info)
    {
        if ($this->category !== $info['category']) {
            $this->output->newLine();
            $this->line('|------------------------------------------');
            $this->line('| Running '.$info['category'].' Checks');
            $this->line('|------------------------------------------');
        }

        $this->output->newLine();
        $this->output->write("<fg=yellow>Check {$this->countAnalyzers}/{$this->totalAnalyzers}: </fg=yellow>");
        $this->output->write($info['title']);
        $this->line(' '.$this->getSymbolForStatus($info['status']));
        $this->updateResult($info);

        if (! in_array($info['status'], ['passed', 'skipped'])) {
            $error = $info['error'] ?? $info['exception'];
            $this->line("<fg=red>{$error}</fg=red>");

            if (! empty($info['traces'])) {
                collect($info['traces'])->take(5)->each(function ($lineNumbers, $path) {
                    $this->line(
                        "<fg=magenta>At ".Str::after($path, base_path()).(empty($lineNumbers) ? "" : ": line(s): ")
                        .collect($lineNumbers)->join(', ', ' and ').".</fg=magenta>"
                    );
                });

                if (count($info['traces']) > 5) {
                    $this->line("<fg=magenta>And "
                        .(count($info['traces']) - 5)
                        ."</fg=magenta> more file(s).");
                }
            }
        }

        $this->category = $info['category'];
        $this->countAnalyzers++;
    }

    /**
     * Initialize the result.
     *
     * @return $this
     */
    protected function initializeResult()
    {
        $this->result = [];

        foreach (['Performance', 'Security', 'Reliability', 'Total'] as $category) {
            $this->result[$category] = [
                'passed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'error' => 0,
            ];
        }

        return $this;
    }

    /**
     * Update the result based on the analysis.
     *
     * @return string
     */
    protected function printReportCard()
    {
        $this->output->newLine();

        $this->output->title('Report Card');

        $rightAlign = (new TableStyle())->setPadType(STR_PAD_LEFT);

        $this->table(
            ['Status', 'Performance', 'Security', 'Reliability', 'Total'],
            collect(['passed', 'failed', 'skipped', 'error'])->map(function ($status) {
                return [
                    $status == 'skipped' ? 'Not Applicable' : ucfirst($status),
                    $this->formatResult($status, 'Performance'),
                    $this->formatResult($status, 'Security'),
                    $this->formatResult($status, 'Reliability'),
                    $this->formatResult($status, 'Total'),
                ];
            })->values()->toArray(),
            'default',
            ['default', $rightAlign, $rightAlign, $rightAlign, $rightAlign]
        );
    }

    /**
     * Get the result with percentage for each category.
     *
     * @param $status
     * @param $category
     * @return string
     */
    protected function formatResult($status, $category) {
        $totalAnalyzersInCategory = (float) collect($this->result[$category])->sum(function ($count) {
            return $count;
        });
        $percentage = round((float) $this->result[$category][$status] * 100 / $totalAnalyzersInCategory, 0);

        return $this->result[$category][$status]
            .str_pad(" ({$percentage}%)", 6, " ", STR_PAD_LEFT);
    }

    /**
     * Update the result based on the analysis.
     *
     * @param array $info
     * @return string
     */
    protected function updateResult(array $info)
    {
        $this->result[$info['category']][$info['status']]++;
        $this->result['Total'][$info['status']]++;
    }

    /**
     * Get the appropriate symbol for the status.
     *
     * @param string $status
     * @return string
     */
    protected function getSymbolForStatus(string $status)
    {
        switch ($status) {
            case 'passed':
                return '<fg=green>Passed</fg=green>';
            case 'failed':
                return '<fg=red>Failed</fg=red>';
            case 'skipped':
                return '<fg=cyan>Not Applicable</fg=cyan>';
            case 'error':
                return '<fg=magenta>Exception</fg=magenta>';
        }

        return '';
    }

    /**
     * Set the console colors for Enlightn's logo.
     *
     * @return void
     */
    protected function setColors()
    {
        collect([
            'e' => 'green',
            'n' => 'yellow',
            'l' => 'red',
            'i' => 'cyan',
            'g' => 'yellow',
            'h' => 'red',
            't' => 'cyan',
            'ns' => 'green',
        ])->each(function ($color, $tag) {
            $this->output->getFormatter()->setStyle($tag, new OutputFormatterStyle($color, 'black'));
        });

    }
}