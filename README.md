# UNIT3D Installer (working!)

Fresh Ubuntu installer for stock UNIT3D Community Edition.

**NOTE:** Run this only on a fresh server with a clean OS install.

## This Repository

Installer for [UNIT3D Community Edition](https://github.com/HDInnovations/UNIT3D).

This fork keeps the stock UNIT3D install flow, with one important addition: it installs and configures Meilisearch automatically so Laravel Scout search is ready after installation.

## Supported OS

Primary target:

- Ubuntu 24.04 LTS (Noble Numbat)

Legacy / compatibility targets from the upstream installer:

- Ubuntu 22.04 LTS (Jammy Jellyfish)
- Ubuntu 20.04 LTS (Focal Fossa)

For long-term production installs, Ubuntu 24.04 LTS is the recommended target.

## What The Installer Sets Up

- PHP and PHP-FPM
- Nginx
- MySQL
- Redis
- Supervisor
- Node.js / Bun frontend build tooling
- Composer dependencies
- Laravel Echo Server
- UNIT3D source code
- `.env` generation
- Laravel key, migrations, seeders, scheduler and workers
- Meilisearch service on `127.0.0.1:7700`
- Laravel Scout Meilisearch environment values
- Meilisearch index settings sync

## Install

```bash
sudo apt -y install git
git clone https://github.com/CudoMudo/UNIT3D-Installer.git installer
cd installer
sudo ./install.sh
```

Follow the prompts. You need a valid domain pointing to the server before enabling SSL.

## Meilisearch

The installer automatically:

- creates a dedicated `meilisearch` system user
- installs the `meilisearch` binary to `/usr/local/bin/meilisearch`
- creates `/etc/meilisearch.toml`
- creates `/etc/systemd/system/meilisearch.service`
- enables and starts the service
- generates a random Meilisearch master key
- writes these values to UNIT3D `.env`:

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=<generated-key>
```

After migrations, the installer runs:

```bash
php artisan scout:sync-index-settings
```

If you import or restore production data later, rebuild searchable data from the installed UNIT3D app as needed.

## Non-HTTPS Installs

If you run UNIT3D without HTTPS, update these settings after install:

```text
.env                         SESSION_SECURE_COOKIE=false
config/secure-headers.php    HTTP Strict Transport Security=false
config/secure-headers.php    Content Security Policy disabled
```

## Notes

- Keep `.env`, database backups, torrent files, user uploads and runtime storage outside Git.
- This installer assumes a fresh server. Running it over an existing production server can remove existing app files.
- For project-specific TorrentHR deployment, use the separate TorrentHR installer.
