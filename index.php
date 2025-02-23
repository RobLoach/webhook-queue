<?php

// Default enviornment variable values.
define('TITLE', 'Webhook Queue');
define('DESCRIPTION', 'Captures webhook calls, and caches them into an RSS queue.');
define('FILE', '.webhooks');
define('DEBUG', true);

// Load dependenices.
require __DIR__ . '/vendor/autoload.php';

// Load any environment parameters.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

app()->config([
    'debug' => _env('DEBUG', DEBUG)
]);

// CORS
app()->cors();

// Check API key.
if (!_env('SERVER_KEY')) {
    response()->json([
        'message' => 'Missing SERVER_KEY'
    ], 503);
    return;
}

// Check the storage system.
// TODO: Use a database instead.
if (!storage()->exists(_env('FILE', FILE))) {
    if (!storage()->createFile(_env('FILE', FILE))) {
        response()->json([
            'message' => 'Filed to create storage file: ' . _env('FILE', FILE) . '. Make the empty file with chmod 777.'
        ], 503);
        return;
    }
}

// Webhook
app()->post('/webhook', function() {
    $token = request()->get('token');
    if (!$token) {
        response()->json([
            'message' => 'Missing token'
        ], 403);
        return;
    }

    if ($token != _env('TOKEN')) {
        response()->json([
            'message' => 'Incorrect token'
        ], 403);
        return;
    }

    // Get the webhook data.
    $data = request()->body();

    // Santize the data
    if (empty($data['0'])) {
        unset($data['0']);
    }

    // Add some custom entries
    $data['time'] = time();

    // Serialize it and save to the file.
    if (!file_put_contents(_env('FILE', FILE), serialize($data) . "\n", FILE_APPEND)) {
        response()->json([
            'message' => 'Failed to save data to ' . _env('FILE', FILE)
        ], 503);
        return;
    }

    response()->json([
        'message' => 'Message received.'
    ]);
});

// Queue
app()->get('/queue', function() {
    $homepage = _env('HOMEPAGE', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . '://' . $_SERVER['HTTP_HOST']);
    $rss = new FeedWriter\RSS2();
    $rss->setTitle(_env('TITLE', TITLE));
    $rss->setDescription(_env('DESCRIPTION', DESCRIPTION));
    $rss->setLink($homepage);

    $key = request()->get('server_key');
    if (!$key) {
        $errorItem = $rss->createNewItem();
        $errorItem->setTitle('Error: Missing Server Key');
        $errorItem->setDescription('Make sure to add a "server_key" when retrieving the queue.');
        $errorItem->setDate(time());
        $errorItem->setLink($homepage);
        $rss->addItem($errorItem);
        response()->xml($rss->generateFeed(), 401);
        return;
    }

    if ($key != _env('SERVER_KEY')) {
        $errorItem = $rss->createNewItem();
        $errorItem->setTitle('Error: Incorrect Server Key');
        $errorItem->setDescription('Make sure to provide the correct "server_key".');
        $errorItem->setDate(time());
        $errorItem->setLink($homepage);
        $rss->addItem($errorItem);
        response()->xml($rss->generateFeed(), 401);
        return;
    }

    // Load all the webhook calls.
    $contents = file_get_contents(_env('FILE', FILE));

    // Clear out the file, since the entries will be delivered.
    file_put_contents(_env('FILE', FILE), '');

    // Split the webhooks to individual entries.
    $entries = explode("\n", $contents);
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) {
            continue;
        }
        $entry = unserialize($entry, [
            'allowed_classes' => false
        ]);

        if (!$entry['time']) {
            continue;
        }

        $item = $rss->createNewItem();
        $item->setTitle($entry['title'] ?? 'Webhook');

        if (isset($entry['description'])) {
            $item->setDescription($entry['description']);
            unset($entry['description']);
        }

        $item->setDate($entry['time']);
        unset($entry['time']);

        if (isset($entry['link'])) {
            $item->setLink($entry['link']);
            unset($entry['link']);
        }
        else {
            $item->setLink($homepage);
        }
        $item->addElementArray($entry);

        $rss->addItem($item);
    }

    response()->xml($rss->generateFeed());
});

// Run the application.
app()->run();
