<?php

date_default_timezone_set('Europe/Moscow');

include 'vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;

const FLOOD_WAIT = 5;

function askLink(string $prompt): string
{
    while (true) {
        $value = readline($prompt);

        if (!preg_match('/https:\/\/t\.me\/([a-zA-Z0-9+_\-]{17})/', $value, $match)) {
            Logger::log("ERROR!Wrong link. try again.", LOG_ERR);
            continue;
        }

        return $value;
    }
}

function forwardMessages(array $batch): void
{
    global $MadelineProto, $session;

    while (true) {
        $forwarded = $MadelineProto->messages->forwardMessages([
            'from_peer' => $session['forwarding_from'],
            'id' => $batch,
            'to_peer' => $session['forwarding_to'],
        ]);

        if (detectFlood($forwarded) === true) {
            Logger::log("FLOOD_WAIT detected, waiting " . FLOOD_WAIT . " seconds and try again...");
            sleep(FLOOD_WAIT);
            continue;
        }

        Logger::log('Successful forwarded messages: ' . count($batch));
        break;
    }
}

function detectFlood($responseData): bool
{
    $check = print_r($responseData, true);

    if (stripos($check, 'FLOOD_WAIT') !== false) {
        return true;
    }

    return false;
}

$settings = new Settings;
$MadelineProto = new API('session.madeline');
$MadelineProto->start();
$sessionFile = 'session.json';
$session = [
    'forwarding_from' => null,
    'forwarding_to' => null,
    'last_message_id' => 0,
];

if (!file_exists($sessionFile)) {
    file_put_contents($sessionFile, json_encode($session));
} else {
    $session = file_get_contents('session.json');
    $session = json_decode($session, true);
}

if (empty($session['forwarding_from'])) {
    $session['forwarding_from'] = askLink("Enter full join link for forwarding from group, ex.: https://t.me/+Rfx1NZtR-8liZTli: ");
}

if (empty($session['forwarding_to'])) {
    $session['forwarding_to'] = askLink("Enter full join link for forwarding from group, ex.: https://t.me/+Rfx1NZtR-8liZTli: ");
}

$forwardingMessages = [];

while (true) {
    $messages = $MadelineProto->messages->getHistory([
        'peer' => $session['forwarding_from'],
        'offset_id' => 0,
        'offset_date' => 0,
        'add_offset' => 0,
        'limit' => 20,
        'max_id' => 0,
        'min_id' => $session['last_message_id'],
    ]);

    if (detectFlood($messages) === true) {
        Logger::log(sprintf("FLOOD_WAIT detected, waiting %d seconds and try again...", FLOOD_WAIT));
        sleep(FLOOD_WAIT);
        continue;
    } elseif (!isset($messages['_'])
        || (!in_array($messages['_'], ['messages.messages', 'messages.messagesSlice', 'messages.channelMessages']))
    ) {
        Logger::log("Can't get messages from forwarding_from source!");
        exit(-1);
    }

    $latestMsgId = null;

    foreach ($messages['messages'] as $message) {
        if ($message['_'] !== 'message') {
            continue;
        }

        if ($latestMsgId === null) {
            $latestMsgId = $message['id'];
        }

        // skip old messages to prevent forwarding messages flood
        if (time() - $message['date'] > 3600) {
            continue;
        }

        $forwardingMessages[] = $message['id'];
    }

    if ($latestMsgId !== null) {
        $session['last_message_id'] = $latestMsgId;
    }

    break;
}

if (!empty($forwardingMessages)) {
    $forwardingMessages = array_reverse($forwardingMessages);
    $batchSize = 10;
    $batch = [];
    $current = 0;

    while (!empty($forwardingMessages)) {
        $forwarding = array_shift($forwardingMessages);
        $batch[] = $forwarding;
        $current++;

        if ($current >= $batchSize) {
            forwardMessages($batch);
            $batch = [];
            $current = 0;
        }
    }

    if (!empty($batch)) {
        forwardMessages($batch);
    }
}

file_put_contents($sessionFile, json_encode($session));
