#!/bin/bash
# Compose Manager - Backup Cron Script
# Called by cron to create scheduled backups.

PLUGIN_ROOT="/usr/local/emhttp/plugins/compose.manager"
LOG_TAG="compose.manager"

logger -t "$LOG_TAG" "[backup] Scheduled backup starting..."

# Execute the backup via the PHP backend
result=$(php -r "
  \$_POST = ['action' => 'createBackup'];
  require_once('${PLUGIN_ROOT}/php/defines.php');
  require_once('${PLUGIN_ROOT}/php/backup_functions.php');
  \$r = createBackup();
  echo json_encode(\$r);
")

# Parse all result fields in a single php invocation instead of 5 separate calls
eval $(echo "$result" | php -r '
  $j = json_decode(file_get_contents("php://stdin"), true) ?: [];
  $fields = [
    "status"  => $j["result"]  ?? "error",
    "message" => $j["message"] ?? "Unknown error",
    "archive" => $j["archive"] ?? "",
    "size"    => $j["size"]    ?? "",
    "stacks"  => $j["stacks"]  ?? "0",
  ];
  foreach ($fields as $k => $v) {
    printf("%s=%s\n", $k, escapeshellarg($v));
  }
')

if [ "$status" = "success" ]; then
    logger -t "$LOG_TAG" "[backup] Scheduled backup completed: $archive ($size, $stacks stacks)"
else
    logger -t "$LOG_TAG" "[backup] Scheduled backup FAILED: $message"
fi
