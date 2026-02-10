# Configuration

Access settings via **Settings → Compose** in the Unraid web UI. The settings page has three tabs: **Settings**, **Backup/Restore**, and **Log**.

## Settings Tab

### General

| Setting | Default | Description |
|---------|---------|-------------|
| **Output Style** | Terminal | Choose between terminal (ttyd) or basic output for compose operations |
| **Projects Folder** | `/boot/config/plugins/compose.manager/projects` | Location where compose project directories are stored |
| **Autostart Force Recreate** | No | Force recreate containers during autostart |

### Display

| Setting | Default | Description |
|---------|---------|-------------|
| **Show in Header Menu** | No | Display Compose Manager as a separate page in the header menu |
| **Show Compose on Top** | No | Show compose stacks above Docker containers on the Docker tab |
| **Hide Compose from Docker** | No | Hide compose-managed containers from the Docker containers table |
| **Show Dashboard Tile** | Yes | Display a Compose Stacks tile on the Dashboard |
| **Hide Compose from Docker Tile** | No | Hide compose containers from the Dashboard Docker tile |

### Update Checking

| Setting | Default | Description |
|---------|---------|-------------|
| **Auto Check for Updates** | No | Automatically check for container image updates on page load |
| **Auto Check Interval** | 1 day | How often to recheck (0.04 = hourly, 1 = daily, 7 = weekly) |
| **Clear Update Cache** | — | Button to clear cached update status if results seem incorrect |

### Advanced

| Setting | Default | Description |
|---------|---------|-------------|
| **Debug Logging** | No | Log detailed compose information to syslog |
| **Patch Docker Page** | No | Patch the native Docker page (Unraid 6.11 and earlier only; not needed on 6.12+) |

## Output Styles

### Terminal (ttyd)

- Full terminal output with colors and real-time updates
- Interactive terminal session
- Best for debugging and watching build progress

### Basic

- Simple text output
- Lower resource usage
- Good for headless or automated operations

## Projects Folder

The default location stores all compose configurations on the USB flash drive, ensuring they persist across reboots.

**Structure:**
```
/boot/config/plugins/compose.manager/projects/
├── stack-name/
│   ├── docker-compose.yml
│   ├── docker-compose.override.yml (optional)
│   ├── .env (optional)
│   ├── profiles (auto-generated)
│   └── default_profile (optional)
└── another-stack/
    └── ...
```

## Backup / Restore Tab

### Backup Settings

| Setting | Default | Description |
|---------|---------|-------------|
| **Backup Destination** | `/boot/config/plugins/compose.manager/backups` | Path where backup archives (`.tar.gz`) are stored |
| **Backups to Keep** | 5 | Number of archives to retain; oldest are deleted after a new backup. Set to 0 for unlimited |
| **Scheduled Backup** | No | Enable automatic scheduled backups via cron |
| **Schedule Frequency** | Daily | Daily or weekly backup schedule |
| **Schedule Day** | Monday | Day of week for weekly backups |
| **Schedule Time** | 03:00 | Time of day for scheduled backups |

### Backup Operations

- **Save Settings** — Persist backup destination, retention, and schedule configuration
- **Backup Now** — Immediately create a `.tar.gz` archive of all stack directories

### Restore Operations

- **Available Backups** — Lists backup archives from the configured destination
- **Browse for Archive** — Select a `.tar.gz` file from elsewhere on the system
- **Delete Selected** — Remove a backup archive from disk
- **Stacks in Archive** — After selecting an archive, lists all stacks it contains with checkboxes
- **Restore Selected** — Extract selected stacks from the archive into the projects folder, overwriting existing files

## Web UI Patches

> **Note:** Patches are only needed on Unraid 6.11 and earlier. On Unraid 6.12+, compose integration is built-in and this setting is automatically disabled.

When enabled, Compose Manager patches the native Docker manager to:

- Show compose containers grouped by stack
- Display stack status indicators
- Add compose-specific actions to container context menus

Patches are version-specific and located in `source/compose.manager/patches/`.

## Log Tab

The Log tab provides a real-time view of compose-related syslog entries.

- **Lines** — Number of log lines to display (50–1000)
- **Refresh** — Auto-refresh interval (manual, 5s, 10s, 30s, 60s)
- **Filter** — Search/filter log entries
- **Download** — Export logs as a text file
- **Auto-scroll** — Automatically scroll to newest entries

Enable **Debug Logging** to troubleshoot issues. Logs are written to the Unraid syslog.

View logs from the command line with:
```bash
tail -f /var/log/syslog | grep compose
```
