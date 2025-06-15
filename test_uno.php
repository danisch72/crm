<?php
// test-token.php - TEMPORANEO
session_start();
?>
<!DOCTYPE html>
<html>
<head><title>Test Token</title></head>
<body>
<h3>Test Token CSRF</h3>
<p>Session ID: <?= session_id() ?></p>
<p>Token in sessione: <?= $_SESSION['auth_token'] ?? 'NESSUNO' ?></p>
<p>Session data: <pre><?= print_r($_SESSION, true) ?></pre></p>
</body>
</html>