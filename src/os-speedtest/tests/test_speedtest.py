"""Rebuild the rendered result from the only output that reports progress."""
import importlib.util
from pathlib import Path
import unittest

SCRIPT = Path(__file__).resolve().parents[1] / 'src/usr/local/opnsense/scripts/speedtest/speedtest.py'
spec = importlib.util.spec_from_file_location('speedtest', SCRIPT)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

# Captured from speedtest-go 1.7.10 on the target, piped rather than on a terminal.
REAL = '''
    speedtest-go v1.7.10 (git-1395781) @showwin

ISP: 24.109.53.194 (Shaw Communications) [49.1963, -122.8106] \r
Found 20 Public Servers\r

Test Server: [16781] 22.72km Vancouver, BC (Canada) by TELUS Mobility\r
Latency: 1.465513ms Jitter: 347.804µs Min: 1.007929ms Max: 1.914407ms\r
Packet Loss Analyzer: Running in background (<= 30 Secs)\r
Download: 267.69 Mbps (Used: 340.07MB) (Latency: 9ms Jitter: 1ms Min: 7ms Max: 10ms)\r
Upload: 21.55 Mbps (Used: 30.10MB) (Latency: 12ms Jitter: 2ms Min: 9ms Max: 20ms)\r
Packet Loss: 0.00% (Sent: 119/Dup: 0/Max: 118)\r
'''


class ParseTests(unittest.TestCase):
    def test_every_stage_is_reported_in_order(self):
        stages, _ = m.parse(REAL)
        self.assertEqual(['isp', 'servers', 'server', 'latency', 'loss_started',
                          'download', 'upload', 'loss'], [s['stage'] for s in stages])

    def test_a_partial_run_reports_only_what_finished(self):
        partial = REAL.split('Download:')[0]
        stages, data = m.parse(partial)
        self.assertEqual(['isp', 'servers', 'server', 'latency', 'loss_started'],
                         [s['stage'] for s in stages])
        # Without a download figure there is nothing worth rendering yet.
        self.assertIsNone(m.result_of(data))

    def test_the_rebuilt_result_carries_the_units_the_page_renders(self):
        _, data = m.parse(REAL)
        entry = m.result_of(data)['servers'][0]
        # The page multiplies by 8/1e6, so throughput has to be bytes per second.
        self.assertAlmostEqual(267.69, entry['dl_speed'] * 8 / 1e6, places=2)
        self.assertAlmostEqual(21.55, entry['ul_speed'] * 8 / 1e6, places=2)
        # Latency is nanoseconds, as the Go marshaller writes it.
        self.assertAlmostEqual(1.465513, entry['latency'] / 1e6, places=6)
        self.assertAlmostEqual(0.347804, entry['jitter'] / 1e6, places=6)
        self.assertEqual('16781', entry['id'])
        self.assertEqual('Vancouver, BC (Canada)', entry['name'])
        self.assertEqual('TELUS Mobility', entry['sponsor'])
        self.assertAlmostEqual(22.72, entry['distance'])
        self.assertEqual({'sent': 119, 'dup': 0, 'max': 118}, entry['packet_loss'])

    def test_the_isp_line_survives_a_missing_coordinate(self):
        _, data = m.parse('ISP: 1.2.3.4 (Example ISP)\n')
        self.assertEqual('1.2.3.4', data['isp']['ip'])
        self.assertEqual('Example ISP', data['isp']['isp'])

    def test_other_rate_units_scale_correctly(self):
        for text, mbps in (('Download: 890.00 Kbps (Used: 1MB)', 0.89),
                           ('Download: 1.20 Gbps (Used: 1MB)', 1200.0),
                           ('Download: 267.69 Mbps (Used: 1MB)', 267.69)):
            _, data = m.parse('Test Server: [1] 1.00km A by B\n' + text + '\n')
            self.assertAlmostEqual(mbps, m.result_of(data)['servers'][0]['dl_speed'] * 8 / 1e6,
                                   places=2, msg=text)

    def test_durations_in_every_unit_go_prints(self):
        for text, ns in (('9ms', 9e6), ('347.804µs', 347804.0), ('1.5s', 1.5e9), ('12ns', 12.0)):
            self.assertAlmostEqual(ns, m.duration_ns(text), places=3, msg=text)

    def test_an_unmeasurable_packet_loss_is_not_invented(self):
        _, data = m.parse(REAL.replace('Packet Loss: 0.00% (Sent: 119/Dup: 0/Max: 118)',
                                       'Packet Loss: N/A'))
        self.assertNotIn('packet_loss', m.result_of(data)['servers'][0])


class ConcurrencyTests(unittest.TestCase):
    """Two tests at once share the link and both report a fraction of it."""

    def test_a_second_run_refuses_while_one_holds_the_lock(self):
        import fcntl, os, subprocess, sys, tempfile
        with tempfile.TemporaryDirectory() as state:
            source = SCRIPT.read_text().replace("STATE = '/var/db/speedtest'",
                                                "STATE = %r" % state)
            # A binary that cannot exist proves the lock is taken before it runs.
            source = source.replace("BINARY = '/usr/local/bin/opnsense-speedtest'",
                                    "BINARY = %r" % os.path.join(state, 'absent'))
            script = Path(state) / 'runner.py'
            script.write_text(source)
            held = open(os.path.join(state, 'run.lock'), 'a')
            fcntl.flock(held, fcntl.LOCK_EX | fcntl.LOCK_NB)
            done = subprocess.run([sys.executable, str(script)], capture_output=True, text=True)
            self.assertEqual(1, done.returncode)
            self.assertIn('already running', done.stderr)
            held.close()
            # With the lock free it gets past the guard and fails on the binary instead.
            done = subprocess.run([sys.executable, str(script)], capture_output=True, text=True)
            self.assertNotIn('already running', done.stderr)
