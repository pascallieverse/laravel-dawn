<?php

namespace Dawn\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateServiceCommand extends Command
{
    protected $signature = 'dawn:service
        {type? : The service type to generate (supervisor, systemd, launchd, windows)}
        {--user= : The system user to run Dawn as (auto-detected on Forge)}
        {--log= : Log file path (default: storage/logs/dawn.log)}';

    protected $description = 'Generate a service configuration for your platform';

    public function handle(): int
    {
        $type = $this->argument('type');

        if (! $type) {
            $type = $this->choice('Which service manager do you use?', [
                'supervisor',
                'systemd',
                'launchd',
                'windows',
            ], $this->detectDefaultChoice());
        }

        return match ($type) {
            'supervisor' => $this->generateSupervisor(),
            'systemd' => $this->generateSystemd(),
            'launchd' => $this->generateLaunchd(),
            'windows' => $this->generateWindows(),
            default => $this->invalidType($type),
        };
    }

    protected function generateSupervisor(): int
    {
        $config = $this->resolveConfig();
        $name = $config['service_name'];

        $conf = <<<CONF
        [program:{$name}]
        process_name=%(program_name)s
        command={$config['dawn_bin']} --working-dir {$config['app_dir']} --php {$config['php_bin']} --log-level info
        autostart=true
        autorestart=true
        user={$config['user']}
        redirect_stderr=true
        stdout_logfile={$config['log_file']}
        stopwaitsecs=3600
        stopsignal=SIGTERM
        CONF;

        $conf = $this->dedent($conf);
        $outputPath = base_path("{$name}-supervisor.conf");

        file_put_contents($outputPath, $conf . "\n");

        $this->info("Supervisor config written to {$outputPath}");
        $this->newLine();
        $this->line('To install:');
        $this->line("  sudo cp {$outputPath} /etc/supervisor/conf.d/{$name}.conf");
        $this->line('  sudo supervisorctl reread');
        $this->line('  sudo supervisorctl update');
        $this->line("  sudo supervisorctl start {$name}");

        return 0;
    }

    protected function generateSystemd(): int
    {
        $config = $this->resolveConfig();
        $name = $config['service_name'];

        $unit = <<<UNIT
        [Unit]
        Description=Dawn Queue Manager ({$config['app_name']})
        After=network.target redis-server.service

        [Service]
        Type=simple
        User={$config['user']}
        Group={$config['user']}
        WorkingDirectory={$config['app_dir']}
        ExecStart={$config['dawn_bin']} --working-dir {$config['app_dir']} --php {$config['php_bin']} --log-file {$config['log_file']} --log-level info
        Restart=always
        RestartSec=5
        KillSignal=SIGTERM
        TimeoutStopSec=3600

        [Install]
        WantedBy=multi-user.target
        UNIT;

        $unit = $this->dedent($unit);
        $outputPath = base_path("{$name}.service");

        file_put_contents($outputPath, $unit . "\n");

        $this->info("systemd unit written to {$outputPath}");
        $this->newLine();
        $this->line('To install:');
        $this->line("  sudo cp {$outputPath} /etc/systemd/system/{$name}.service");
        $this->line('  sudo systemctl daemon-reload');
        $this->line("  sudo systemctl enable {$name}");
        $this->line("  sudo systemctl start {$name}");

        return 0;
    }

    protected function resolveConfig(): array
    {
        $appDir = base_path();
        $appName = config('app.name', 'laravel');
        $logFile = $this->option('log') ?: storage_path('logs/dawn.log');
        $user = $this->option('user') ?: $this->detectUser($appDir);
        $serviceName = 'dawn-' . Str::slug($appName);

        return [
            'app_dir' => $appDir,
            'app_name' => $appName,
            'service_name' => $serviceName,
            'dawn_bin' => $appDir . '/vendor/bin/dawn',
            'php_bin' => PHP_BINARY,
            'user' => $user,
            'log_file' => $logFile,
        ];
    }

    /**
     * Detect the system user to run Dawn as.
     *
     * On Laravel Forge servers the project lives under /home/{user}/...
     * Standard (non-isolated) sites use the "forge" user. Isolated sites
     * run under a dedicated user whose name matches the site directory,
     * e.g. /home/myapp.com/myapp.com → user "myapp.com".
     */
    protected function detectUser(string $appDir): string
    {
        // Forge: project lives under /home/{user}/...
        if (preg_match('#^/home/([^/]+)/#', $appDir, $matches)) {
            $user = $matches[1];

            if ($user === 'forge') {
                $this->comment('Detected Forge server (standard mode) — using user "forge".');
            } else {
                $this->comment("Detected Forge isolated site — using user \"{$user}\".");
            }

            return $user;
        }

        // Fallback: owner of the project directory
        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $owner = posix_getpwuid(fileowner($appDir));

            if ($owner && $owner['name'] !== 'root') {
                $this->comment("Using project directory owner \"{$owner['name']}\".");

                return $owner['name'];
            }
        }

        return 'www-data';
    }

    protected function generateLaunchd(): int
    {
        $config = $this->resolveConfig();
        $name = $config['service_name'];
        $label = 'com.dawn.' . Str::slug($config['app_name']);

        $plist = <<<PLIST
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
        <plist version="1.0">
        <dict>
            <key>Label</key>
            <string>{$label}</string>
            <key>ProgramArguments</key>
            <array>
                <string>{$config['dawn_bin']}</string>
                <string>--working-dir</string>
                <string>{$config['app_dir']}</string>
                <string>--php</string>
                <string>{$config['php_bin']}</string>
                <string>--log-level</string>
                <string>info</string>
            </array>
            <key>WorkingDirectory</key>
            <string>{$config['app_dir']}</string>
            <key>RunAtLoad</key>
            <true/>
            <key>KeepAlive</key>
            <true/>
            <key>StandardOutPath</key>
            <string>{$config['log_file']}</string>
            <key>StandardErrorPath</key>
            <string>{$config['log_file']}</string>
        </dict>
        </plist>
        PLIST;

        $plist = $this->dedent($plist);
        $outputPath = base_path("{$label}.plist");

        file_put_contents($outputPath, $plist . "\n");

        $this->info("launchd plist written to {$outputPath}");
        $this->newLine();
        $this->line('To install as a user agent (runs when you log in):');
        $this->line("  cp {$outputPath} ~/Library/LaunchAgents/{$label}.plist");
        $this->line("  launchctl load ~/Library/LaunchAgents/{$label}.plist");
        $this->newLine();
        $this->line('To install as a system daemon (runs at boot):');
        $this->line("  sudo cp {$outputPath} /Library/LaunchDaemons/{$label}.plist");
        $this->line("  sudo launchctl load /Library/LaunchDaemons/{$label}.plist");
        $this->newLine();
        $this->line('To stop:');
        $this->line("  launchctl unload ~/Library/LaunchAgents/{$label}.plist");

        return 0;
    }

    protected function generateWindows(): int
    {
        $config = $this->resolveConfig();
        $name = $config['service_name'];
        $dawnBin = str_replace('/', '\\', $config['dawn_bin']);
        $appDir = str_replace('/', '\\', $config['app_dir']);
        $phpBin = str_replace('/', '\\', $config['php_bin']);
        $logFile = str_replace('/', '\\', $config['log_file']);

        $bat = <<<BAT
        @echo off
        REM Dawn Queue Manager - {$config['app_name']}
        REM Run this script to start Dawn in the foreground.
        REM For a Windows Service, use NSSM (see instructions below).

        "{$dawnBin}" --working-dir "{$appDir}" --php "{$phpBin}" --log-file "{$logFile}" --log-level info
        BAT;

        $bat = $this->dedent($bat);
        $batPath = base_path("{$name}.bat");

        file_put_contents($batPath, $bat . "\r\n");

        $this->info("Startup script written to {$batPath}");
        $this->newLine();
        $this->line('To run Dawn in the foreground:');
        $this->line("  {$name}.bat");
        $this->newLine();
        $this->line('To install as a Windows Service using NSSM (https://nssm.cc):');
        $this->line("  nssm install {$name} \"{$dawnBin}\"");
        $this->line("  nssm set {$name} AppParameters \"--working-dir \\\"{$appDir}\\\" --php \\\"{$phpBin}\\\" --log-file \\\"{$logFile}\\\" --log-level info\"");
        $this->line("  nssm set {$name} AppDirectory \"{$appDir}\"");
        $this->line("  nssm set {$name} Description \"Dawn Queue Manager ({$config['app_name']})\"");
        $this->line("  nssm set {$name} Start SERVICE_AUTO_START");
        $this->line("  nssm start {$name}");
        $this->newLine();
        $this->line('To uninstall:');
        $this->line("  nssm stop {$name}");
        $this->line("  nssm remove {$name} confirm");

        return 0;
    }

    /**
     * Detect the best default choice based on the current OS.
     */
    protected function detectDefaultChoice(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 3; // windows
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            return 2; // launchd
        }

        // Linux — default to supervisor
        return 0;
    }

    protected function invalidType(string $type): int
    {
        $this->error("Unknown service type: {$type}. Use 'supervisor', 'systemd', 'launchd', or 'windows'.");

        return 1;
    }

    /**
     * Remove common leading whitespace from a heredoc string.
     */
    protected function dedent(string $text): string
    {
        $lines = explode("\n", $text);
        $minIndent = PHP_INT_MAX;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            $minIndent = min($minIndent, $indent);
        }

        if ($minIndent === PHP_INT_MAX) {
            return $text;
        }

        return implode("\n", array_map(
            fn ($line) => trim($line) === '' ? '' : substr($line, $minIndent),
            $lines,
        ));
    }
}
