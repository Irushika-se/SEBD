<?php
// Run every minute from the command line (NOT from the web), so urgency escalation and auto-closing of expired
// requests happen even when nobody is logged in:
//   Windows Task Scheduler (WAMP):  C:\wamp64\bin\php\php8.x\php.exe C:\wamp64\www\SEBD\cron\process_requests.php   (repeat every 1 minute)
//   Linux cron:                     * * * * * php /var/www/SEBD/cron/process_requests.php
if(PHP_SAPI!=='cli'){ http_response_code(403); exit('Command line only.'); }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
$admin=$pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE' ORDER BY user_id LIMIT 1")->fetchColumn();
if(!$admin){ fwrite(STDERR,"No active administrator account found.\n"); exit(1); }
$_SESSION=['uid'=>(int)$admin]; // history rows written by the automatic widening are attributed to this admin
process_requests($pdo,true);
campaign_auto_complete($pdo);
try{ $pdo->prepare("INSERT INTO system_status(status_key,status_value) VALUES('last_cron_run',?) ON DUPLICATE KEY UPDATE status_value=VALUES(status_value),updated_at=NOW()")->execute([date('Y-m-d H:i:s')]); }catch(Throwable $e){ fwrite(STDERR,"Heartbeat not saved (run database/admin_upgrade.sql): ".$e->getMessage()."\n"); } // shown on the admin dashboard
echo date('Y-m-d H:i:s')," process_requests done\n";
