#!/usr/local/bin/php
<?php

const CONFIG_FILE = '/conf/config.xml';
const STATE_DIR = '/var/db/os-mihomo';
const STATE_FILE = STATE_DIR . '/unbound-state.json';
const FORWARD_UUID = 'b126bf65-a985-49ca-a9d2-16f156aac198';
const FAKE_IP_CIDR = '198.18.0.0/15';

function childElement(DOMDocument $doc, DOMElement $parent, string $name, string $value = ''): DOMElement
{
    $node = $doc->createElement($name);
    if ($value !== '') {
        $node->appendChild($doc->createTextNode($value));
    }
    $parent->appendChild($node);
    return $node;
}

function setChild(DOMDocument $doc, DOMElement $parent, string $name, string $value): void
{
    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMElement && $child->tagName === $name) {
            $child->nodeValue = $value;
            return;
        }
    }
    childElement($doc, $parent, $name, $value);
}

$mode = $argv[1] ?? '';
if (!in_array($mode, ['install', 'uninstall'], true)) {
    fwrite(STDERR, "usage: setup_unbound.php install|uninstall\n");
    exit(64);
}

$lock = fopen('/var/run/os-mihomo-unbound.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX)) {
    fwrite(STDERR, "unable to lock Unbound configuration\n");
    exit(1);
}

$doc = new DOMDocument();
$doc->preserveWhiteSpace = true;
if (!$doc->load(CONFIG_FILE)) {
    fwrite(STDERR, "unable to read " . CONFIG_FILE . "\n");
    exit(1);
}
$xpath = new DOMXPath($doc);
$unbound = $xpath->query('/opnsense/OPNsense/unboundplus')->item(0);
if (!$unbound instanceof DOMElement) {
    fwrite(STDERR, "OPNsense Unbound model was not found\n");
    exit(1);
}

$forwarding = $xpath->query('./forwarding/enabled', $unbound)->item(0);
$privateAddress = $xpath->query('./advanced/privateaddress', $unbound)->item(0);
$dots = $xpath->query('./dots', $unbound)->item(0);
if (!$forwarding instanceof DOMElement || !$privateAddress instanceof DOMElement || !$dots instanceof DOMElement) {
    fwrite(STDERR, "OPNsense Unbound model is incomplete\n");
    exit(1);
}

if ($mode === 'install') {
    if (!is_dir(STATE_DIR)) {
        mkdir(STATE_DIR, 0700, true);
    }
    if (!file_exists(STATE_FILE)) {
        $addresses = array_values(array_filter(array_map('trim', explode(',', $privateAddress->textContent))));
        file_put_contents(STATE_FILE, json_encode([
            'forwarding_enabled' => trim($forwarding->textContent),
            'had_fake_ip_private_address' => in_array(FAKE_IP_CIDR, $addresses, true),
        ], JSON_PRETTY_PRINT) . "\n", LOCK_EX);
        chmod(STATE_FILE, 0600);
    }

    $forwarding->nodeValue = '0';
    $addresses = array_values(array_filter(
        array_map('trim', explode(',', $privateAddress->textContent)),
        static fn($address) => $address !== '' && $address !== FAKE_IP_CIDR
    ));
    $privateAddress->nodeValue = implode(',', $addresses);

    $dot = $xpath->query('./dot[@uuid="' . FORWARD_UUID . '"]', $dots)->item(0);
    if (!$dot instanceof DOMElement) {
        $dot = $doc->createElement('dot');
        $dot->setAttribute('uuid', FORWARD_UUID);
        $dots->appendChild($dot);
    }
    setChild($doc, $dot, 'enabled', '1');
    setChild($doc, $dot, 'type', 'forward');
    setChild($doc, $dot, 'domain', '');
    setChild($doc, $dot, 'server', '127.0.0.1');
    setChild($doc, $dot, 'port', '1053');
    setChild($doc, $dot, 'verify', '');
    setChild($doc, $dot, 'forward_tcp_upstream', '0');
    setChild($doc, $dot, 'forward_first', '0');
    setChild($doc, $dot, 'description', 'Mihomo DNS forwarding');
} else {
    foreach (iterator_to_array($xpath->query('./dot[@uuid="' . FORWARD_UUID . '"]', $dots)) as $dot) {
        $dots->removeChild($dot);
    }
    $state = file_exists(STATE_FILE) ? json_decode((string)file_get_contents(STATE_FILE), true) : null;
    if (is_array($state)) {
        $forwarding->nodeValue = (string)($state['forwarding_enabled'] ?? '0');
        if (!empty($state['had_fake_ip_private_address'])) {
            $addresses = array_values(array_filter(array_map('trim', explode(',', $privateAddress->textContent))));
            if (!in_array(FAKE_IP_CIDR, $addresses, true)) {
                $addresses[] = FAKE_IP_CIDR;
                $privateAddress->nodeValue = implode(',', $addresses);
            }
        }
    }
}

$backup = CONFIG_FILE . '.bak.os-mihomo-unbound-' . date('Ymd-His');
copy(CONFIG_FILE, $backup);
$temp = CONFIG_FILE . '.os-mihomo.tmp';
if ($doc->save($temp) === false || !rename($temp, CONFIG_FILE)) {
    @unlink($temp);
    fwrite(STDERR, "unable to update " . CONFIG_FILE . "\n");
    exit(1);
}

if ($mode === 'uninstall') {
    @unlink(STATE_FILE);
    @rmdir(STATE_DIR);
}
