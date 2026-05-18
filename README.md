# ⚡ Advanced Database Manager for Pelican Panel

<p align="center">
  <img src="https://img.shields.io/badge/Pelican_Panel-v1.0.0--beta33-blue?style=for-the-badge&logo=laravel&logoColor=white&color=6C5CE7" alt="Pelican Version">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-blue?style=for-the-badge&logo=php&logoColor=white&color=a855f7" alt="PHP Version">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge&color=2ecc71" alt="License">
</p>

---

<p align="center">
  <img src="https://github.com/user-attachments/assets/34080af1-c1f3-4183-b05d-9f6a9d3204b1" alt="Advanced Database Manager Showcase" width="100%"/>
>
</p>

---

Add a beautiful **live database size column** and handy client-facing **SQL backup, download, restore, and delete actions** directly to the server database page in Pelican Panel.

---

## ✨ Features

- 📊 **Live Database Size**: See your database storage footprint in real-time inside your database list.
- 🚀 **High Performance**: Features a built-in 60-second database size cache, ensuring absolutely zero panel lag or InnoDB locking.
- 💾 **One-Click Client Backups**: Clients can instantly create, download, restore, or delete backups with absolute ease.
- 🧹 **Automated Retention**: Self-cleaning safety system. Only **10 backups** will remain per database, and the older ones keep getting deleted automatically as new ones are created.
- 🛡️ **Zero Core Bloat**: Installs as a completely self-contained, isolated plugin that doesn't modify a single Pelican core file.

---

## 🛠️ Requirements

- `mysqldump` and `mysql` binaries available on the Panel host.
- Panel host can connect to the configured database hosts.

---

## 🚀 Installation

You can install this plugin directly from your Pelican panel's built-in **Plugin Manager**.

If you prefer to install it manually:

1. Go to the [Releases](https://github.com/laalaalaee/database-tools/releases) page.
2. Download the **`database-tools.zip`** asset (do **not** download the raw "Source code" zip!).
3. Extract the contents and upload the `database-tools` folder to your panel's `plugins/database-tools` directory.
4. Run the following commands inside your panel's root directory:

```bash
cd /var/www/pelican
php artisan migrate --path=plugins/database-tools/database/migrations --force
php artisan optimize:clear
```

---

## ⚙️ Configuration

To customize the plugin's behavior, you can optionally append these keys to your Pelican `.env` file at `/var/www/pelican/.env`:

| Key | Default | Description |
| :--- | :---: | :--- |
| `DATABASE_TOOLS_DISK` | `local` | Storage disk (maps to `storage/app/private/` by default). |
| `DATABASE_TOOLS_PATH` | `database-backups` | Subdirectory where backups are saved. |
| `DATABASE_TOOLS_SIZE_CACHE` | `60` | Cache duration (seconds) for database sizes. |
| `DATABASE_TOOLS_RETENTION` | `10` | Automatic retention limit (only 10 remain and the old ones keep getting deleted). Set to `0` to keep everything forever. |
| `DATABASE_TOOLS_MYSQLDUMP` | `mysqldump` | Path to the `mysqldump` binary. |
| `DATABASE_TOOLS_MYSQL` | `mysql` | Path to the `mysql` binary. |
| `DATABASE_TOOLS_BACKUP_TIMEOUT` | `300` | Process timeout in seconds for database dumps. |
| `DATABASE_TOOLS_RESTORE_TIMEOUT` | `300` | Process timeout in seconds for database restores. |

---

## 📂 Backup Directory Structure

Physical backup files are saved securely under your Pelican private storage:
`/var/www/pelican/storage/app/private/database-backups/server-<ID>/<database_name>/`

---

## 💡 A few quick things to keep in mind

- **What users see:** In the Pelican Panel UI, this plugin is beautifully labeled as **Advanced Database Manager**.
- **Under the hood:** The folder stays named `plugins/database-tools` internally to make sure it plays nicely with Pelican's internal plugin loading system and Linux file paths.
- **Security & Permissions:** Don't worry about access control! All backup actions (create, download, restore, or delete) automatically check and respect standard Pelican user database permissions.

---

<p align="center">
  Made with ❤️ by <b>laalaalaee</b>
</p>
