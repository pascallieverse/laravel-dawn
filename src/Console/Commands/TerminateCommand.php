<?php

namespace Dawn\Console\Commands;

use Dawn\Contracts\CommandQueue;
use Dawn\Contracts\MasterSupervisorRepository;
use Dawn\DawnServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

class TerminateCommand extends Command
{
    protected $signature = 'dawn:terminate
        {--wait : Block until all Dawn supervisors have exited}
        {--timeout=60 : Seconds to wait when --wait is set}';

    protected $description = 'Terminate all Dawn processes';

    public function handle(
        CommandQueue $commands,
        MasterSupervisorRepository $masters,
        RedisFactory $redis,
    ): int {
        $signalled = $this->signalRegisteredMasters($masters, $commands, $redis);
        $signalled += $this->signalLocalOrphans();

        if ($signalled === 0) {
            $this->warn('No Dawn supervisors found to terminate.');
        } else {
            $this->info("Sent SIGTERM to {$signalled} Dawn process(es).");
        }

        if ($this->option('wait')) {
            $timeout = max(1, (int) $this->option('timeout'));

            if (! $this->waitForShutdown($masters, $timeout)) {
                $this->error("Dawn supervisors did not terminate within {$timeout}s.");

                return 1;
            }

            $this->info('All Dawn supervisors have exited.');
        }

        return 0;
    }

    /**
     * Signal each master registered in Redis. Same-host masters get SIGTERM
     * by PID (bypassing the Redis command queue); cross-host masters fall
     * back to the Redis command queue.
     */
    protected function signalRegisteredMasters(
        MasterSupervisorRepository $masters,
        CommandQueue $commands,
        RedisFactory $redis,
    ): int {
        $hostname = gethostname() ?: 'unknown';
        $prefix = DawnServiceProvider::resolvePrefix();
        $connection = $redis->connection('dawn');
        $signalled = 0;

        foreach ($masters->names() as $master) {
            $localMaster = is_string($master) && str_starts_with($master, "dawn-{$hostname}-");

            if ($localMaster && $this->signalLocalMaster($connection, $prefix, $master)) {
                $signalled++;

                continue;
            }

            $commands->push($master, 'terminate', [
                'wait' => (bool) $this->option('wait'),
            ]);
        }

        return $signalled;
    }

    /**
     * Send SIGTERM to a same-host master PID looked up from Redis.
     */
    protected function signalLocalMaster($connection, string $prefix, string $master): bool
    {
        $pid = (int) $connection->get($prefix . 'master:' . $master . ':pid');

        if ($pid <= 0) {
            return false;
        }

        return $this->kill($pid);
    }

    /**
     * Fallback: scan the local host for any `dawn-linux-x64` (or equivalent)
     * processes and SIGTERM them directly. Handles the case where a master
     * is running but failed to register itself in Redis.
     *
     * Linux-only; relies on procfs. No-op on other platforms.
     */
    protected function signalLocalOrphans(): int
    {
        if (! is_dir('/proc')) {
            return 0;
        }

        $signalled = 0;
        $selfPid = (int) getmypid();

        foreach ($this->enumerateDawnBinaryPids($selfPid) as $pid) {
            if ($this->kill($pid)) {
                $signalled++;
            }
        }

        return $signalled;
    }

    /**
     * Poll until no masters remain registered and no local dawn binaries are
     * running, or the timeout elapses. Returns true on clean shutdown.
     */
    protected function waitForShutdown(MasterSupervisorRepository $masters, int $timeout): bool
    {
        $deadline = microtime(true) + $timeout;
        $selfPid = (int) getmypid();

        while (microtime(true) < $deadline) {
            $registered = ! empty($masters->names());

            $localRunning = false;
            foreach ($this->enumerateDawnBinaryPids($selfPid) as $_) {
                $localRunning = true;
                break;
            }

            if (! $registered && ! $localRunning) {
                return true;
            }

            usleep(250_000);
        }

        return false;
    }

    /**
     * Yield PIDs of running dawn binaries on this host, excluding the given PID.
     *
     * @return \Generator<int>
     */
    protected function enumerateDawnBinaryPids(int $excludePid): \Generator
    {
        if (! is_dir('/proc')) {
            return;
        }

        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $path) {
            $pid = (int) basename(dirname($path));

            if ($pid <= 0 || $pid === $excludePid) {
                continue;
            }

            $cmdline = @file_get_contents($path);

            if ($cmdline === false || $cmdline === '') {
                continue;
            }

            // /proc/<pid>/cmdline is NUL-separated; argv[0] is the binary path.
            $argv0 = strstr($cmdline, "\0", true) ?: $cmdline;
            $basename = basename($argv0);

            // Match the distributed binaries (dawn-linux-x64, dawn-linux-arm64)
            // and the plain `dawn` name (used in dev or when renamed).
            if (str_starts_with($basename, 'dawn-') || $basename === 'dawn') {
                yield $pid;
            }
        }
    }

    /**
     * Send SIGTERM to a PID. Returns true if the signal was accepted (or the
     * process is already gone).
     */
    protected function kill(int $pid): bool
    {
        if (! function_exists('posix_kill')) {
            return false;
        }

        // SIGTERM = 15
        return @posix_kill($pid, 15);
    }
}
