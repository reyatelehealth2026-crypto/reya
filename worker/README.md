# Notification Worker

Background worker for processing Odoo notification queue with retry support and roadmap batching.

## Features

- ✅ Async notification processing
- ✅ Automatic retry on failure
- ✅ Roadmap batch processing
- ✅ Queue management
- ✅ Auto-cleanup old items
- ✅ Statistics logging

## Setup

### Option 1: Cron Job (Recommended)

Add to crontab:

```bash
# Run every minute
* * * * * cd /path/to/re-ya && php worker/notification-worker.php >> logs/notification-worker.log 2>&1 &
```

### Option 2: Systemd Service

Create `/etc/systemd/system/notification-worker.service`:

```ini
[Unit]
Description=Odoo Notification Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/re-ya
ExecStart=/usr/bin/php /path/to/re-ya/worker/notification-worker.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl enable notification-worker
sudo systemctl start notification-worker
sudo systemctl status notification-worker
```

### Option 3: Supervisor

Create `/etc/supervisor/conf.d/notification-worker.conf`:

```ini
[program:notification-worker]
command=/usr/bin/php /path/to/re-ya/worker/notification-worker.php
directory=/path/to/re-ya
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/path/to/re-ya/logs/notification-worker.log
```

Reload supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start notification-worker
```

## Monitoring

### View Logs

```bash
tail -f logs/notification-worker.log
```

### Check Queue Status

```bash
mysql -u user -p database -e "SELECT status, COUNT(*) FROM odoo_notification_queue GROUP BY status;"
```

### Check Batch Groups

```bash
mysql -u user -p database -e "SELECT status, COUNT(*) FROM odoo_notification_batch_groups GROUP BY status;"
```

## Configuration

Worker runs for 1 hour then stops (to prevent memory leaks). Cron/systemd will restart it automatically.

### Adjust Settings

Edit `worker/notification-worker.php`:

- **Poll interval**: Change `sleep(5)` (default: 5 seconds)
- **Batch size**: Change `getPending(10)` (default: 10 notifications)
- **Runtime**: Change `3600` (default: 1 hour)
- **Cleanup age**: Change `cleanup(7)` (default: 7 days)

## Troubleshooting

### Worker not processing

1. Check if worker is running:
   ```bash
   ps aux | grep notification-worker
   ```

2. Check database connection:
   ```bash
   php -r "require 'config/database.php'; echo 'OK';"
   ```

3. Check logs:
   ```bash
   tail -100 logs/notification-worker.log
   ```

### High error rate

1. Check LINE API credentials in `line_accounts` table
2. Check network connectivity to LINE API
3. Review error messages in logs
4. Check `odoo_notification_log` table for patterns

### Notifications stuck in queue

1. Check worker is running
2. Check for failed notifications:
   ```sql
   SELECT * FROM odoo_notification_queue WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 10;
   ```
3. Manually retry:
   ```sql
   UPDATE odoo_notification_queue SET status = 'pending', retry_count = 0 WHERE status = 'failed';
   ```

## Performance

- Processes ~10 notifications per cycle (5 seconds)
- Can handle ~120 notifications per minute
- Automatic retry with exponential backoff
- Auto-cleanup prevents table bloat

## Statistics

Worker logs statistics every 5 minutes showing:
- Queue status counts
- Average retry counts
- Processed/error counts
