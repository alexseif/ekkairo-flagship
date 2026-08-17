<?php

namespace EkaAlexandria\Migration\Utils;

class Logger
{
    private string $logFile;
    private bool $echoOutput;

    public function __construct(string $logFile, bool $echoOutput = true)
    {
        $this->logFile = $logFile;
        $this->echoOutput = $echoOutput;
        StyleSanitizer::initLogFile($this->logFile);
    }

    public function log(string $msg, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[{$timestamp}] [{$level}] {$msg}\n";
        file_put_contents($this->logFile, $formatted, FILE_APPEND);

        if ($this->echoOutput) {
            if (class_exists('WP_CLI')) {
                if ($level === 'ERROR') {
                    \WP_CLI::warning("ERROR: " . $msg);
                } elseif ($level === 'WARNING') {
                    \WP_CLI::warning($msg);
                } else {
                    \WP_CLI::line($msg);
                }
            } else {
                echo $formatted;
            }
        }
    }
}
