<?php

namespace Dawn\Livewire\Concerns;

trait ParsesException
{
    /**
     * Parse the exception string into structured data.
     * Format: "ClassName: message in /path/to/file.php:123"
     */
    protected function parseException(string $exception): array
    {
        $class = null;
        $message = $exception;
        $file = null;
        $line = null;

        // Try to extract "ClassName: message in /path:line"
        if (preg_match('/^([\w\\\\]+):\s*(.+?)\s+in\s+(.+):(\d+)$/s', $exception, $m)) {
            $class = $m[1];
            $message = $m[2];
            $file = $m[3];
            $line = (int) $m[4];
        } elseif (preg_match('/^([\w\\\\]+):\s*(.+)$/s', $exception, $m)) {
            $class = $m[1];
            $message = $m[2];
        }

        return [
            'class' => $class,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ];
    }

    /**
     * Parse a PHP stack trace string into structured frames.
     */
    protected function parseTrace(string $trace): array
    {
        $frames = [];
        $lines = explode("\n", $trace);

        foreach ($lines as $line) {
            $line = trim($line);
            if (! preg_match('/^#(\d+)\s+(.+)$/', $line, $m)) {
                continue;
            }

            $index = (int) $m[1];
            $rest = $m[2];
            $file = null;
            $lineNum = null;
            $call = $rest;

            // Pattern: /path/file.php(123): Class->method()
            if (preg_match('/^(.+?)\((\d+)\):\s*(.+)$/', $rest, $fm)) {
                $file = $fm[1];
                $lineNum = (int) $fm[2];
                $call = $fm[3];
            }
            // Pattern: {main}
            elseif ($rest === '{main}') {
                $call = '{main}';
            }

            $isVendor = $file && (
                str_contains($file, '/vendor/') ||
                ! str_starts_with($file, base_path())
            );

            $frames[] = [
                'index' => $index,
                'file' => $file,
                'line' => $lineNum,
                'call' => $call,
                'isVendor' => $isVendor,
                'snippet' => null, // filled lazily
            ];
        }

        return $frames;
    }

    /**
     * Read a code snippet around a specific line from a file.
     * Returns an array of ['lineNumber' => 'code'] pairs.
     */
    protected function readSnippet(?string $file, ?int $line, int $context = 8): array
    {
        if (! $file || ! $line || ! file_exists($file)) {
            return [];
        }

        $lines = @file($file);
        if (! $lines) {
            return [];
        }

        $start = max(0, $line - $context - 1);
        $end = min(count($lines), $line + $context);

        $snippet = [];
        for ($i = $start; $i < $end; $i++) {
            $snippet[$i + 1] = rtrim($lines[$i]);
        }

        return $snippet;
    }

    /**
     * Shorten a file path relative to base_path for display.
     */
    protected function shortenPath(?string $path): string
    {
        if (! $path) {
            return '';
        }

        $base = base_path() . '/';
        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base));
        }

        return $path;
    }

    /**
     * Find the Laravel log file to read.
     * Checks single log, daily log (by date), and the most recent log file.
     */
    protected function findLogFile(?float $timestamp): ?string
    {
        $logsDir = storage_path('logs');

        // 1. Single channel: laravel.log
        $single = $logsDir . '/laravel.log';
        if (file_exists($single)) {
            return $single;
        }

        // 2. Daily channel: laravel-YYYY-MM-DD.log matching the timestamp
        if ($timestamp) {
            $date = date('Y-m-d', (int) $timestamp);
            $daily = $logsDir . '/laravel-' . $date . '.log';
            if (file_exists($daily)) {
                return $daily;
            }
        }

        // 3. Fallback: most recent laravel-*.log file
        $files = glob($logsDir . '/laravel-*.log');
        if ($files) {
            usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));
            return $files[0];
        }

        return null;
    }

    /**
     * Extract log entries from the Laravel log file around a timestamp.
     */
    protected function getLogsAroundTimestamp(?float $timestamp): array
    {
        if (! $timestamp) {
            return [];
        }

        $logPath = $this->findLogFile($timestamp);

        if (! $logPath || ! file_exists($logPath)) {
            return [];
        }

        $failedTime = \Carbon\Carbon::createFromTimestamp($timestamp);
        $from = $failedTime->copy()->subSeconds(2);
        $to = $failedTime->copy()->addSeconds(3);

        $logs = [];
        $currentEntry = null;

        $handle = fopen($logPath, 'r');
        if (! $handle) {
            return [];
        }

        $fileSize = filesize($logPath);
        $maxScan = 512 * 1024;
        $startPos = max(0, $fileSize - $maxScan);
        fseek($handle, $startPos);

        if ($startPos > 0) {
            fgets($handle);
        }

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                if ($currentEntry && $currentEntry['inRange']) {
                    $logs[] = $currentEntry;
                }

                try {
                    $entryTime = \Carbon\Carbon::parse($matches[1]);
                    $inRange = $entryTime->between($from, $to);
                } catch (\Exception $e) {
                    $inRange = false;
                    $entryTime = null;
                }

                if ($entryTime && $entryTime->gt($to->copy()->addMinute())) {
                    break;
                }

                $currentEntry = [
                    'timestamp' => $matches[1],
                    'inRange' => $inRange,
                    'text' => $line,
                ];
            } elseif ($currentEntry) {
                $currentEntry['text'] .= $line;
            }
        }

        if ($currentEntry && $currentEntry['inRange']) {
            $logs[] = $currentEntry;
        }

        fclose($handle);

        return $logs;
    }
}
