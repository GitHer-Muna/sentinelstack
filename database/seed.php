<?php
declare(strict_types=1);

/**
 * SentinelStack — seed script.
 *
 * Idempotent: safe to run multiple times. 
 * Inserts affirmations and ensures the database is fully primed.
 */

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer dependencies are missing. Run: composer install\n");
    exit(1);
}
require $autoload;

App\App::boot($root);

$dbPath = App\Env::get('DB_PATH', './data/sentinelstack.sqlite');
$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

// Reset cached singleton in case the DB file changed under us (e.g. a
// prior process held an open handle to a file we just deleted).
App\Database::reset();

$pdo = App\Database::connect($dbPath);

// Apply schema first (also idempotent)
$schema = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schema);

echo "Schema applied.\n";

// ============================================================
// Affirmations — 60 original, non-cliché reflective prompts.
// ============================================================
// 62 reflective prompts. The set deliberately avoids the
// "you-are-allowed-to / you-do-not-have-to / it-is-fine-to"
// permission chorus and the parallel essay-shape that AI copy
// tends toward. Instead: concrete objects, asymmetric lengths,
// wry register, occasional non-second-person observations.
$affirmations = [
    'Lunch is not optional.',
    'The walk to the kitchen counts.',
    'Cold water from the tap is enough.',
    'Half a load of laundry was a load.',
    'Tuesday again.',
    'Where the rain hits the gutter is a good place to listen.',
    'The bin only needs taking out when it needs taking out.',
    'A small win is just a thing that happened.',
    'Tired is one of today\'s facts.',
    'You can do a thing badly and call it done.',
    'Pasta at 6 is not a failure.',
    'The cat does not keep track.',
    'Sleep is progress.',
    'Birds, mostly, are doing their best.',
    'A long bath does not count as indulgence.',
    'Three deep breaths. That\'s how many.',
    'Your plant is fine. Probably.',
    'There is quiet in the next ten minutes.',
    'A snack is dinner if dinner is late.',
    'Light through a window is doing something.',
    'The shop near you is closer than the one in your head.',
    'Nobody is grading how you load the dishwasher.',
    'The kettle is not a metaphor.',
    'Five minutes outside is five minutes outside.',
    'A phone call to no one is still rest.',
    'The shower is one of the better rooms.',
    'You already know what to do with the next ten seconds.',
    'Tea is not a personality but it can be a morning.',
    'The dog does not need a reason.',
    'A long errand and a short nap cancel out to nothing.',
    'The window doesn\'t have to be open.',
    'One book, halfway, is fine.',
    'A loud clock is still a clock.',
    'The post is whatever was on the way.',
    'Stretching is the body asking nicely.',
    'Soup is a valid three meals in a row.',
    'The list will not run away.',
    'Mornings get easier on the second cup.',
    'Birds in the morning, again.',
    'Reading the same page again is not a problem.',
    'The fridge at 11 is different from the fridge at 7.',
    'Socks go in pairs if you let them.',
    'The hallway is not a problem.',
    'A short walk beats a long think.',
    'You don\'t need to announce the end of a break.',
    'The kettle is loud and we like that.',
    'A thing undone today is not a thing owed tomorrow.',
    'Wind through a window is weather, not instruction.',
    'Talk out loud to yourself. Nobody minds.',
    'The light in the kitchen at this hour is the good kind.',
    'Snacks at 4 are snacks at 4 everywhere.',
    'A washcloth is a reasonable friendship with tomorrow.',
    'Quiet rooms are not empty.',
    'A short list is enough.',
    'The trip to the corner counts as a trip.',
    'A long Tuesday is still a Tuesday.',
    'The news reads the same on a second read.',
    'The door is the same door every time.',
    'A small thing done twice is just a thing done.',
    'The kitchen is closed only when you say it is.',
    'Tomorrow is named Tuesday but won\'t behave like one.',
    'The light comes back the next day.',
];

$stmt = $pdo->prepare('SELECT COUNT(*) AS n FROM affirmations');
$stmt->execute();
$existing = (int) $stmt->fetchColumn();

if ($existing === 0) {
    $insert = $pdo->prepare('INSERT OR IGNORE INTO affirmations (body) VALUES (:body)');
    foreach ($affirmations as $body) {
        $insert->execute([':body' => $body]);
    }
    echo "Inserted " . count($affirmations) . " affirmations.\n";
} else {
    echo "Affirmations table already populated ({$existing} rows) — skipping.\n";
}

echo "Seed complete.\n";
