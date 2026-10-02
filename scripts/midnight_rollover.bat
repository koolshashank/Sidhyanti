@echo off
cd /d C:\xampp\htdocs\sidhyanti-crm-v2
echo ---- %date% %time% ---- >> C:\Backups\sidhyanti-code-backups\midnight_rollover.log
C:\xampp\php\php.exe scripts\midnight_rollover.php >> C:\Backups\sidhyanti-code-backups\midnight_rollover.log 2>&1
