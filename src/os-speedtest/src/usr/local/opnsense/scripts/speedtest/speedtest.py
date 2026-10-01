#!/usr/local/bin/python3
"""Run a speed test in the background and report its stages while it runs.

speedtest-go emits no progress in JSON mode and no intermediate samples in any
mode: each stage prints one line only once it has finished. Running it in its
readable mode therefore gives the only progress signal available, so the result
is rebuilt from that text into the same JSON the page has always rendered.
"""
import fcntl
import json
import os
import re
import subprocess
import sys
import tempfile
import time

STATE = '/var/db/speedtest'
RESULT = STATE + '/result.json'
PROGRESS = STATE + '/progress.json'
LOCK = STATE + '/run.lock'
BINARY = '/usr/local/bin/opnsense-speedtest'
TIMEOUT = 180

# Every stage the readable output announces, in the order it announces them.
STAGES = (
    ('isp', re.compile(r'^ISP:\s*(?P<ip>\S+)\s*\((?P<isp>[^)]*)\)'
                       r'(?:\s*\[(?P<lat>[-0-9.]+),\s*(?P<lon>[-0-9.]+)\])?')),
    ('servers', re.compile(r'^Found\s+(?P<count>\d+)\s+Public Servers')),
    ('server', re.compile(r'^Test Server:\s*\[(?P<id>\d+)\]\s*(?P<distance>[0-9.]+)km\s*'
                          r'(?P<name>.+?)\s+by\s+(?P<sponsor>.+?)\s*$')),
    ('latency', re.compile(r'^Latency:\s*(?P<latency>\S+)\s+Jitter:\s*(?P<jitter>\S+)')),
    ('loss_started', re.compile(r'^Packet Loss Analyzer:')),
    ('download', re.compile(r'^Download:\s*(?P<rate>[0-9.]+)\s*(?P<unit>[KMG]?bps)')),
    ('upload', re.compile(r'^Upload:\s*(?P<rate>[0-9.]+)\s*(?P<unit>[KMG]?bps)')),
    ('loss', re.compile(r'^Packet Loss:\s*(?P<loss>[0-9.]+)%\s*'
                        r'\(Sent:\s*(?P<sent>\d+)/Dup:\s*(?P<dup>\d+)/Max:\s*(?P<max>\d+)\)')),
    ('loss_absent', re.compile(r'^Packet Loss:\s*N/A')),
)

RATE_UNITS = {'bps': 1, 'Kbps': 1e3, 'Mbps': 1e6, 'Gbps': 1e9}
# Go prints durations with the largest unit that keeps the value above one.
DURATION_UNITS = (('ns', 1), ('µs', 1e3), ('us', 1e3), ('ms', 1e6), ('s', 1e9))


def duration_ns(text):
    """Nanoseconds from a Go duration, which is what the result JSON stores."""
    for suffix, scale in DURATION_UNITS:
        if text.endswith(suffix):
            try:
                return float(text[:-len(suffix)]) * scale
            except ValueError:
                return 0.0
    return 0.0


def bytes_per_second(rate, unit):
    """The result JSON stores throughput as bytes per second, not bits."""
    return float(rate) * RATE_UNITS.get(unit, 1e6) / 8


def parse(text):
    """Stages seen so far, and the finished result once enough of them are."""
    stages, data = [], {}
    for raw in text.replace('\r', '\n').splitlines():
        line = raw.strip().lstrip('✓').strip()
        for name, pattern in STAGES:
            found = pattern.match(line)
            if not found:
                continue
            stages.append({'stage': name, 'text': line})
            data[name] = found.groupdict()
            break
    return stages, data


def result_of(data):
    """Rebuild the JSON shape the page renders, or None while incomplete."""
    if 'server' not in data or 'download' not in data:
        return None
    server = data['server']
    isp = data.get('isp') or {}
    latency = data.get('latency') or {}
    entry = {
        'id': server['id'],
        'name': server['name'],
        'sponsor': server['sponsor'],
        'distance': float(server['distance']),
        'latency': duration_ns(latency.get('latency', '0ns')),
        'jitter': duration_ns(latency.get('jitter', '0ns')),
        'dl_speed': bytes_per_second(data['download']['rate'], data['download']['unit']),
        'ul_speed': (bytes_per_second(data['upload']['rate'], data['upload']['unit'])
                     if 'upload' in data else 0.0),
    }
    if 'loss' in data:
        loss = data['loss']
        entry['packet_loss'] = {'sent': int(loss['sent']), 'dup': int(loss['dup']),
                                'max': int(loss['max'])}
    return {'timestamp': time.strftime('%Y-%m-%d %H:%M:%S'),
            'user_info': {'IP': isp.get('ip', ''), 'Isp': isp.get('isp', ''),
                          'Lat': isp.get('lat') or '', 'Lon': isp.get('lon') or ''},
            'servers': [entry]}


def publish(state, text='', error=''):
    stages, data = parse(text)
    payload = {'state': state, 'stages': stages, 'error': error, 'updated': time.time()}
    write(PROGRESS, payload, 0o644)
    return data


def write(path, payload, mode):
    os.makedirs(STATE, exist_ok=True)
    handle, temporary = tempfile.mkstemp(dir=STATE)
    with os.fdopen(handle, 'w') as stream:
        json.dump(payload, stream)
    os.chmod(temporary, mode)
    os.rename(temporary, path)


def main(argv):
    # Two tests at once share the link and both report a fraction of it.
    os.makedirs(STATE, exist_ok=True)
    guard = open(LOCK, 'a')
    try:
        fcntl.flock(guard, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        print('a speed test is already running', file=sys.stderr)
        return 1
    command = [BINARY, '--unix'] + argv[1:]
    publish('running')
    collected = ''
    try:
        process = subprocess.Popen(command, stdout=subprocess.PIPE,
                                   stderr=subprocess.STDOUT, text=True, bufsize=1)
    except OSError as error:
        publish('failed', error=str(error))
        return 1
    deadline = time.time() + TIMEOUT
    try:
        for line in process.stdout:
            collected += line
            publish('running', collected)
            if time.time() > deadline:
                process.kill()
                publish('failed', collected, 'The speed test exceeded its time limit.')
                return 1
    finally:
        process.stdout.close()
    if process.wait() != 0:
        publish('failed', collected, 'The speed test failed.')
        return 1
    data = publish('running', collected)
    result = result_of(data)
    if result is None:
        publish('failed', collected, 'The speed test produced no usable result.')
        return 1
    write(RESULT, result, 0o600)
    publish('done', collected)
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv))
