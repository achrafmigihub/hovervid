#!/bin/bash

# Configuration
DB_NAME="hovervid_db"
DB_USER="postgres"
BACKUP_DIR="./database/backups"

# Check if backup file is provided
if [ -z "$1" ]; then
    echo "Please provide a backup file name"
    echo "Usage: ./restore-db.sh <backup_file>"
    echo "Available backups:"
    ls -l "$BACKUP_DIR"/*.sql
    exit 1
fi

BACKUP_FILE="$BACKUP_DIR/$1"

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo "Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Create a temporary file to modify the backup
TEMP_BACKUP="${BACKUP_FILE}.temp"
cp "$BACKUP_FILE" "$TEMP_BACKUP"

# Remove the transaction_timeout parameter from the backup
sed -i '' '/SET transaction_timeout = 0;/d' "$TEMP_BACKUP"

# Drop existing database and create a new one
echo "Dropping existing database if it exists..."
PGPASSWORD=postgres_hovervid psql -U $DB_USER -h localhost -p 5432 -c "DROP DATABASE IF EXISTS $DB_NAME;"
PGPASSWORD=postgres_hovervid psql -U $DB_USER -h localhost -p 5432 -c "CREATE DATABASE $DB_NAME;"

# Restore database with clean options
echo "Restoring database..."
PGPASSWORD=postgres_hovervid pg_restore -U $DB_USER -h localhost -p 5432 -d $DB_NAME -v --clean --if-exists --no-owner --no-privileges "$TEMP_BACKUP"

# Clean up temporary file
rm "$TEMP_BACKUP"

if [ $? -eq 0 ]; then
    echo "✅ Database restored successfully from: $BACKUP_FILE"
else
    echo "❌ Database restore failed!"
    exit 1
fi 
