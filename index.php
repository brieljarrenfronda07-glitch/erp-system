<?php
// Refresh changed assets automatically instead of reusing an older logo/template.
function asset(string $path): string {
    return htmlspecialchars($path.'?v='.filemtime(__DIR__.'/'.$path), ENT_QUOTES, 'UTF-8');
}
header('Cache-Control: no-cache, must-revalidate');
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="icon" type="image/png" href="<?= asset('assets/petcare-logo-transparent.png') ?>"><title>Petcare — Animal Clinic</title><link rel="stylesheet" href="<?= asset('style.css') ?>"><link rel="stylesheet" href="<?= asset('print.css') ?>"></head><body data-environment="<?= getenv('APP_ENV') === 'production' ? 'production' : 'local' ?>">
<div id="app"></div><div id="toast" role="status"></div><dialog id="modal"></dialog><script src="<?= asset('app.js') ?>"></script><script src="<?= asset('printing.js') ?>"></script><script src="<?= asset('permissions.js') ?>"></script><script src="<?= asset('activity.js') ?>"></script><script src="<?= asset('billing.js') ?>"></script><script src="<?= asset('workflow.js') ?>"></script><script src="<?= asset('doctor.js') ?>"></script><script src="<?= asset('prescriptions.js') ?>"></script><script src="<?= asset('reports.js') ?>"></script><script src="<?= asset('visits.js') ?>"></script></body></html>
