# Download the current production database

Keep `download-production-db.ps1` and `backup-production-db.sh` in the same folder. Run the PowerShell script on Windows:

```powershell
& 'C:\xampp\htdocs\mahamu360\deploy\download-production-db.ps1' -IdentityFile "$env:USERPROFILE\.ssh\YOUR_PRIVATE_KEY"
```

Omit `-IdentityFile` if your SSH agent already supplies a working production key. Defaults are server `20.151.177.171`, SSH user `adebareshowemimo`, application `/var/www/mahadum`, and server execution as `www-data` through `sudo -n`. Override those parameters if necessary. The SSH identity previously tried by the agent was rejected; this download requires a working identity and noninteractive sudo permission to execute the helper as the application user.

The server must already be in your trusted SSH known-hosts file. Host-key verification is enforced. For a first connection, connect with SSH separately and verify its host fingerprint through your trusted server records before accepting it. The script does not change SSH settings, install tools, grant privileges or update production.

The output is a timestamped `.sql.gz` under `Downloads\Mahadum360-backups`, restricted to your Windows account. `-OutputDirectory 'D:\PrivateBackups'` selects another folder. The script streams the dump directly over SSH, checks the gzip file and prints its SHA256. It removes an incomplete local download on failure. Temporary server credential files have private permissions and are removed when the helper exits; it creates no SQL dump on the server and does not download `.env`.

This exports the configured MySQL/MariaDB database with schema, rows, triggers, routines and events. The application database user needs permission to dump those objects; missing privileges fail the backup. Server client tools (`mariadb-dump` or `mysqldump`), PHP and gzip are required. It uses a live single-transaction snapshot suitable for InnoDB; avoid deployments/migrations or other schema changes while it runs. Nontransactional tables require a separately planned consistent backup.

Gzip validation confirms the downloaded stream is readable. Restoration into an isolated database is still needed to prove restore acceptance. This backup does not contain uploaded videos/images, application code or server configuration.
