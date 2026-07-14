"""
    Alibaba Cloud DNS support for the OPNsense native Dynamic DNS backend.
"""
import base64
import hashlib
import hmac
import syslog
import time
import uuid
from urllib.parse import quote

import requests

from . import BaseAccount


class Aliyun(BaseAccount):
    _priority = 65535

    _services = {
        'aliyun': 'alidns.aliyuncs.com'
    }

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return {'aliyun': 'Aliyun DNS'}

    @staticmethod
    def match(account):
        return account.get('service') in Aliyun._services

    @staticmethod
    def percent_encode(value):
        return quote(str(value), safe='~')

    def request(self, action, parameters):
        params = {
            'Action': action,
            'Format': 'JSON',
            'Version': '2015-01-09',
            'AccessKeyId': self.settings.get('username'),
            'SignatureMethod': 'HMAC-SHA1',
            'Timestamp': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
            'SignatureVersion': '1.0',
            'SignatureNonce': str(uuid.uuid4()),
        }
        params.update(parameters)

        canonical = '&'.join(
            '%s=%s' % (self.percent_encode(key), self.percent_encode(params[key]))
            for key in sorted(params)
        )
        string_to_sign = 'GET&%s&%s' % (self.percent_encode('/'), self.percent_encode(canonical))
        key = (self.settings.get('password') + '&').encode('utf-8')
        signature = base64.b64encode(hmac.new(key, string_to_sign.encode('utf-8'), hashlib.sha1).digest()).decode()
        params['Signature'] = signature

        try:
            response = requests.get(
                'https://%s/' % self._services[self.settings.get('service')],
                params=params,
                headers={'User-Agent': 'OPNsense-dyndns'},
                timeout=10
            )
            response.raise_for_status()
        except requests.RequestException as err:
            syslog.syslog(syslog.LOG_ERR, "Account %s Aliyun DNS request failed [%s] %s" % (
                self.description, action, err
            ))
            return None

        try:
            data = response.json()
        except requests.exceptions.JSONDecodeError:
            syslog.syslog(syslog.LOG_ERR, "Account %s error parsing JSON response [%s] %s" % (
                self.description, action, response.text
            ))
            return None
        if data.get('Code'):
            syslog.syslog(syslog.LOG_ERR, "Account %s Aliyun DNS error [%s] %s %s" % (
                self.description, action, data.get('Code'), data.get('Message')
            ))
            return None
        return data

    def record_name(self, hostname):
        zone = self.settings.get('zone')
        if hostname == zone or hostname == '@':
            return '@'
        suffix = '.%s' % zone
        if hostname.endswith(suffix):
            return hostname[:-len(suffix)]
        return hostname

    def execute(self):
        if super().execute():
            record_type = 'AAAA' if str(self.current_address).find(':') > 1 else 'A'
            ttl = int(self.settings.get('ttl') or 600)
            updated = []

            for hostname in self.settings.get('hostnames', '').split(','):
                rr = self.record_name(hostname)
                payload = self.request('DescribeDomainRecords', {
                    'DomainName': self.settings.get('zone'),
                    'RRKeyWord': rr,
                    'TypeKeyWord': record_type,
                    'PageSize': 500,
                })
                if payload is None:
                    return False

                record_id = None
                for record in payload.get('DomainRecords', {}).get('Record', []):
                    if record.get('RR') == rr and record.get('Type') == record_type:
                        record_id = record.get('RecordId')
                        break

                request_payload = {
                    'RR': rr,
                    'Type': record_type,
                    'Value': str(self.current_address),
                    'TTL': ttl,
                }
                action = 'AddDomainRecord'
                if record_id:
                    request_payload['RecordId'] = record_id
                    action = 'UpdateDomainRecord'
                else:
                    request_payload['DomainName'] = self.settings.get('zone')

                result = self.request(action, request_payload)
                if result is None or not result.get('RecordId'):
                    syslog.syslog(syslog.LOG_ERR, "Account %s failed to set new ip %s for %s" % (
                        self.description, self.current_address, hostname
                    ))
                    return False
                updated.append(hostname)

            syslog.syslog(syslog.LOG_NOTICE, "Account %s set new ip %s %s" % (
                self.description, self.current_address, updated
            ))
            self.update_state(address=self.current_address)
            return True

        return False
