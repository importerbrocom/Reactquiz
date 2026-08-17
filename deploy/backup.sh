#!/bin/bash
# ERO Database Backup Script
# Add to cron: 0 2 * * * /home/uddjzwrz/mediprep.nokkoo.in/deploy/backup.sh

BACKUP_DIR="/home/uddjzwrz/backups"
DB_NAME="uddjzwrz_mediprep"
DB_USER="uddjzwrz_mediprep"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER $DB_NAME > "$BACKUP_DIR/db_$DATE.sql" 2>/dev/null

# Compress
gzip "$BACKUP_DIR/db_$DATE.sql"

# Keep only last 7 days of backups
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete

echo "Backup done: db_$DATE.sql.gz"
