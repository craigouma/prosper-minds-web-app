<?php
/**
 * A stand-in for Google's reCAPTCHA siteverify endpoint, for the local test
 * suite only. The "token" says what Google should answer, so a test can ask for
 * any verdict without a real key or a browser:
 *
 *   good[:action[:score]]   success, score 0.9 unless given
 *   low[:action]            success, score 0.1
 *   score:0.35[:action]     success with that score
 *   noscore                 success with no score (a v2 key)
 *   dup                     timeout-or-duplicate
 *   wronghost               success, but minted on another site
 *   down                    an HTTP 500 that is not JSON
 *
 * A secret starting 'bad-secret' answers invalid-input-secret, as Google does.
 */
header('Content-Type: application/json');

$secret   = (string) ($_POST['secret'] ?? '');
$response = (string) ($_POST['response'] ?? '');
[$kind, $a, $b] = array_pad(explode(':', $response, 3), 3, '');

if ($secret === '') { echo json_encode(['success' => false, 'error-codes' => ['missing-input-secret']]); exit; }
if (str_starts_with($secret, 'bad-secret')) { echo json_encode(['success' => false, 'error-codes' => ['invalid-input-secret']]); exit; }

$ok = static fn (float $score, string $action): array => [
    'success' => true, 'score' => $score, 'action' => $action,
    'hostname' => 'prosper-minds.com', 'challenge_ts' => gmdate('c'),
];

switch ($kind) {
    case 'good':      echo json_encode($ok($b !== '' ? (float) $b : 0.9, $a)); break;
    case 'low':       echo json_encode($ok(0.1, $a)); break;
    case 'score':     echo json_encode($ok((float) $a, $b)); break;
    case 'noscore':   echo json_encode(['success' => true, 'hostname' => 'prosper-minds.com']); break;
    case 'dup':       echo json_encode(['success' => false, 'error-codes' => ['timeout-or-duplicate']]); break;
    case 'wronghost': echo json_encode(['success' => true, 'score' => 0.9, 'action' => $a, 'hostname' => 'evil.example']); break;
    case 'down':      http_response_code(500); echo 'upstream error'; break;
    default:          echo json_encode(['success' => false, 'error-codes' => ['invalid-input-response']]);
}
