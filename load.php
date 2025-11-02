<?php
$s3='https://aeims-temp-setup-32222.s3.us-east-1.amazonaws.com/setup.php?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIAXQIQAFIBA4QQAWLR%2F20251102%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20251102T164730Z&X-Amz-Expires=7200&X-Amz-SignedHeaders=host&X-Amz-Signature=eb69eeb3ebb4a698c5185c88cd5e06b909d92bbef1da4c1d7436d13a477e0684';
file_put_contents(__DIR__.'/setup-full-platform-test.php',file_get_contents($s3));
header('Location: /setup-full-platform-test.php?key=setup2025');
