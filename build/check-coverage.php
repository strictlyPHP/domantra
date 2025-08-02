<?php
echo "Check code coverage\n";
$data = stream_get_contents(STDIN);
if (strstr($data, "Unable to detect executable lines that were changed")) {
    echo "No testable files changed\n";
    exit;
}

preg_match('/[0-9]+(\.[0-9]+)%/',$data, $matches);
$percentActual = floatval($matches[0] ?? 0);
$percentNeeded = 95;
if ($percentActual < $percentNeeded) {
    echo $data;
    throw new \Exception(sprintf(
        'Coverage is only %d percent it needs to be at least %d percent',
        $percentActual,
        $percentNeeded
    ));
}
echo sprintf("Coverage for request is %d percent\n", $percentActual);