<?php

namespace App\Installer\UNIT3D;

use App\Installer\BaseInstaller;

class Unit3dSetup extends BaseInstaller
{
    public function handle()
    {
        $this->clone();

        $this->meilisearch();

        $this->env();

        $this->perms();

        $this->crons();

        $this->setup();

    }

    protected function clone()
    {
        $this->io->writeln('<fg=blue>Cloning Source Files</>');
        $this->seperator();

        $install_dir = $this->config->os('install_dir');
        $url = $this->config->app('repository');

        if (is_dir($install_dir)) {
            $this->process(["rm -rf $install_dir"]);
        }

        $this->process(["git clone $url $install_dir"]);

        if (!is_dir($install_dir)) {
            $this->throwError('Something went wrong with the cloning process. Please report this bug!');
        }
    }

    protected function env()
    {
        $this->io->writeln("\n\n<fg=blue>Preparing the '.env' File</>");
        $this->seperator();

        $install_dir = $this->config->os('install_dir');

        if (file_exists("$install_dir/.env")) {
            $this->process(["rm $install_dir/.env"]);
        }

        $this->createFromStub(
            [
                '{{PROTOCOL}}' => $this->config->app('ssl') == 'yes' ? 'https' : 'http',
                '{{FQDN}}' => $this->config->app('hostname'),
                '{{DBDRIVER}}' => strtolower($this->config->app('database_driver')),
                '{{DB}}' => $this->config->app('db'),
                '{{DBUSER}}' => $this->config->app('dbuser'),
                '{{DBPASS}}' => $this->config->app('dbpass'),
                '{{OWNER}}' => $this->config->app('owner'),
                '{{OWNEREMAIL}}' => $this->config->app('owner_email'),
                '{{OWNERPASSWORD}}' => $this->config->app('password'),
                '{{TMDBAPIKEY}}' => $this->config->app('tmdb-key'),
                '{{MAILDRIVER}}' => $this->config->app('mail_driver'),
                '{{MAILHOST}}' => $this->config->app('mail_host'),
                '{{MAILPORT}}' => $this->config->app('mail_port'),
                '{{MAILUSERNAME}}' => $this->config->app('mail_username'),
                '{{MAILPASSWORD}}' => $this->config->app('mail_password'),
                '{{MAILFROMNAME}}' => $this->config->app('mail_from_name'),
                '{{MEILISEARCHKEY}}' => $this->meilisearchKey(),
            ],
            '../.env.stub',
            "$install_dir/.env"
        );

        $this->io->writeln('<fg=green>OK</>');
    }

    protected function perms()
    {
        $this->io->writeln("\n<fg=blue>Setting Permissions</>");
        $this->seperator();

        $install_dir = $this->config->os('install_dir');
        $web_user = $this->config->os('web-user');

        $this->process([
            "chown -R $web_user:$web_user /etc/letsencrypt",
            "chown -R $web_user:$web_user " . dirname($install_dir),
            "find $install_dir -type d -exec chmod 0775 '{}' + -or -type f -exec chmod 0664 '{}' +",
            "chmod 750 $install_dir/artisan",
            "chmod 640 $install_dir/.env"
        ]);
    }

    protected function meilisearch()
    {
        $this->io->writeln("\n\n<fg=blue>Installing Meilisearch</>");
        $this->seperator();

        $key = $this->meilisearchKey();

        $this->process([
            "getent group meilisearch >/dev/null 2>&1 || groupadd --system meilisearch",
            "id meilisearch >/dev/null 2>&1 || useradd --system --gid meilisearch --home /var/lib/meilisearch --shell /usr/sbin/nologin meilisearch",
            "if ! command -v meilisearch >/dev/null 2>&1; then tmp_dir=$(mktemp -d) && cd \$tmp_dir && curl -L https://install.meilisearch.com | bash && install -m 0755 meilisearch /usr/local/bin/meilisearch && rm -rf \$tmp_dir; fi",
            "mkdir -p /var/lib/meilisearch/data /var/lib/meilisearch/dumps /var/lib/meilisearch/snapshots",
            "chown -R meilisearch:meilisearch /var/lib/meilisearch",
            "chmod 750 /var/lib/meilisearch",
        ]);

        file_put_contents('/etc/meilisearch.toml', <<<TOML
db_path = "/var/lib/meilisearch/data"
env = "production"
http_addr = "127.0.0.1:7700"
master_key = "$key"
no_analytics = true
http_payload_size_limit = "100 MB"
log_level = "INFO"
max_indexing_memory = "2 GiB"
max_indexing_threads = 4
dump_dir = "/var/lib/meilisearch/dumps"
snapshot_dir = "/var/lib/meilisearch/snapshots"
schedule_snapshot = false
TOML);

        file_put_contents('/etc/systemd/system/meilisearch.service', <<<'SERVICE'
[Unit]
Description=Meilisearch
After=network.target

[Service]
Type=simple
User=meilisearch
Group=meilisearch
ExecStart=/usr/local/bin/meilisearch --config-file-path /etc/meilisearch.toml
Restart=on-failure
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
SERVICE);

        $this->process([
            'chown root:meilisearch /etc/meilisearch.toml',
            'chmod 640 /etc/meilisearch.toml',
            'systemctl daemon-reload',
            'systemctl enable --now meilisearch',
        ]);
    }

    protected function setup()
    {
        $this->io->writeln("\n\n<fg=blue>Setting Up Web Site</>");
        $this->seperator();

        $install_dir = $this->config->os('install_dir');
        $fqdn = $this->config->app('hostname');
        $web_user = $this->config->os('web-user');
        $echo_port = $this->config->app('echo-port');
        $protocol = $this->config->app('ssl') == 'yes' ? 'https' : 'http';

        $this->createFromStub([
            '{{FQDN}}' => $fqdn,
            '{{PORT}}' => $echo_port,
            '{{PROTOCOL}}' => $protocol,
        ], '../laravel-echo-server.stub', '/var/www/html/laravel-echo-server.json');
        
        $this->process([
            "chown -R $web_user:$web_user $install_dir/laravel-echo-server.json",
        ]);

        $this->createFromStub([
            '{{INSTALLDIR}}' => $install_dir,
            '{{WEBUSER}}' => $web_user,
        ], 'supervisor/app.conf', '/etc/supervisor/conf.d/unit3d.conf');

        $this->process([
            'supervisorctl reread',
            'supervisorctl update',
            'supervisorctl reload'
        ]);

        $www_cmds = [
            'laravel-echo-server client:add',
            'composer install -q',
            'bun install',
            'bun run build',
            'php artisan key:generate',
            'php artisan migrate --seed',
            'php artisan scout:sync-index-settings',
            'php artisan auto:email-blacklist-update',
            'php artisan test:email'
        ];

        foreach ($www_cmds as $cmd) {
            $this->process([
                "su $web_user -s /bin/bash --command=\"cd $install_dir && $cmd\""
            ], true);
        }

        $this->io->writeln(' ');
    }

    protected function crons()
    {
        $this->io->writeln("\n\n<fg=blue>Setting Up Crontabs</>");
        $this->seperator();

        $install_dir = $this->config->os('install_dir');

        $this->process([
            "(crontab -l ; echo \"* * * * * php $install_dir/artisan schedule:run >> /dev/null 2>&1\") | crontab -"
        ]);
    }

    protected function meilisearchKey()
    {
        $key = $this->config->app('meilisearch-key');

        if (empty($key)) {
            $key = bin2hex(random_bytes(32));
            $this->config->app('meilisearch-key', $key);
        }

        return $key;
    }

}
