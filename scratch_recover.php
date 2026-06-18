<?php
$logFile = 'C:\\Users\\Pia\\.gemini\\antigravity-ide\\brain\\ae2d4904-2b7f-436e-9059-1bd972f32d99\\.system_generated\\logs\\transcript.jsonl';
$lines = file($logFile);

$targetIdx = 22;
if (isset($lines[$targetIdx])) {
    $data = json_decode($lines[$targetIdx], true);
    $content = $data['content'] ?? '';
    echo "Content length in characters: " . strlen($content) . "\n";
    file_put_contents('verify_raw_debug.txt', $content);
} else {
    echo "Line $targetIdx not found.\n";
}
