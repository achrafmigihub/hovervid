#!/bin/bash

# Configuration
DB_NAME="hovervid_db"
DB_USER="postgres"
BACKUP_DIR="./database/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Create backup
PGPASSWORD=postgres_hovervid pg_dump -U $DB_USER -h localhost -p 5432 -F c -b -v -f "$BACKUP_FILE" $DB_NAME

# Keep only the last 5 backups
ls -t "$BACKUP_DIR"/*.sql | tail -n +6 | xargs -r rm

echo "Backup created: $BACKUP_FILE" 
