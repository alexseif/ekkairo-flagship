<?php

namespace EkaAlexandria\Migration\Tests\Utils;

use PHPUnit\Framework\TestCase;
use EkaAlexandria\Migration\Utils\Logger;

class LoggerTest extends TestCase
{
    private string $tempLogFile;

    protected function setUp(): void
    {
        $this->tempLogFile = sys_get_temp_dir() . '/test-logger-' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempLogFile)) {
            unlink($this->tempLogFile);
        }
    }

    public function testLoggerWritesFormattedMessagesToFile(): void
    {
        $logger = new Logger($this->tempLogFile, false); // false = quiet echo
        $logger->log("Test message 1", "INFO");
        $logger->log("Error encountered", "ERROR");

        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString("[INFO] Test message 1", $content);
        $this->assertStringContainsString("[ERROR] Error encountered", $content);
    }
}
