"""
    Copyright (c) 2024 AnShen <root@lshell.com>
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
"""
import json
import syslog
import time
import hashlib
import hmac
import requests
from datetime import datetime
from . import BaseAccount


# dnspod api 3.0
# https://cloud.tencent.com/document/api/1427

class DNSPod_CN(BaseAccount):
    _priority = 65535

    _services = {
        'dnspodcn': 'dnspod.tencentcloudapi.com',
        'tencentcloud': 'dnspod.tencentcloudapi.com'
    }

    def __init__(self, account: dict):
        super().__init__(account)
        self.service = 'dnspod'

    @staticmethod
    def known_services():
        return  {
            'dnspodcn': 'dnspodcn',
            'tencentcloud': 'Tencent Cloud DNS'
        }

    @staticmethod
    def match(account):
        return account.get('service') in DNSPod_CN._services


    @staticmethod
    def _sign(key, msg):
        """
        Generate HMAC-SHA256 signature.

        Args:
            key (bytes): Signing key
            msg (str): Message to sign

        Returns:
            bytes: Signature digest
        """
        return hmac.new(key, msg.encode("utf-8"), hashlib.sha256).digest()

    def generate_signature(self, action, payload="{}"):
        """
        Generate signature and headers for a Tencent Cloud API request.

        Args:
            action (str): API action name
            payload (str or dict, optional): Request payload. Defaults to "{}".

        Returns:
            tuple: Request headers and canonical request
        """
        # Ensure payload is a string
        payload = json.dumps(payload) if isinstance(payload, dict) else payload

        # Get current timestamp
        timestamp = int(time.time())
        date = datetime.utcfromtimestamp(timestamp).strftime("%Y-%m-%d")

        # Step 1: Create Canonical Request
        http_request_method = "POST"
        canonical_uri = "/"
        canonical_querystring = ""
        ct = "application/json; charset=utf-8"
        canonical_headers = f"content-type:{ct}\nhost:{self._services[self.settings.get('service')]}\nx-tc-action:{action.lower()}\n"
        signed_headers = "content-type;host;x-tc-action"
        hashed_request_payload = hashlib.sha256(payload.encode("utf-8")).hexdigest()

        canonical_request = (
            f"{http_request_method}\n"
            f"{canonical_uri}\n"
            f"{canonical_querystring}\n"
            f"{canonical_headers}\n"
            f"{signed_headers}\n"
            f"{hashed_request_payload}"
        )

        # Step 2: Create String to Sign
        algorithm = "TC3-HMAC-SHA256"
        credential_scope = f"{date}/{self.service}/tc3_request"
        hashed_canonical_request = hashlib.sha256(canonical_request.encode("utf-8")).hexdigest()

        string_to_sign = (
            f"{algorithm}\n"
            f"{timestamp}\n"
            f"{credential_scope}\n"
            f"{hashed_canonical_request}"
        )

        # Step 3: Calculate Signature
        secret_date = self._sign(("TC3" + self.settings.get('password')).encode("utf-8"), date)
        secret_service = self._sign(secret_date, self.service)
        secret_signing = self._sign(secret_service, "tc3_request")
        signature = hmac.new(secret_signing, string_to_sign.encode("utf-8"), hashlib.sha256).hexdigest()

        # Step 4: Create Authorization Header
        authorization = (
            f"{algorithm} "
            f"Credential={self.settings.get('username', '')}/{credential_scope}, "
            f"SignedHeaders={signed_headers}, "
            f"Signature={signature}"
        )

        # Prepare headers
        headers = {
            "Authorization": authorization,
            "Content-Type": "application/json; charset=utf-8",
            "Host": self._services[self.settings.get('service')],
            "X-TC-Action": action,
            "X-TC-Timestamp": str(timestamp),
            "X-TC-Version": "2021-03-23",
            'User-Agent': 'OPNsense-dyndns',
        }

        return headers, payload

    def send_request(self, action, payload="{}", region="", token=""):
        """
        Send a request to the Tencent Cloud API.

        Args:
            action (str): API action name
            payload (str or dict, optional): Request payload. Defaults to "{}".
            region (str, optional): Optional region parameter
            token (str, optional): Optional token parameter

        Returns:
            dict: API response JSON
        """
        # Get headers and prepared payload
        headers, payload = self.generate_signature(action, payload)

        # Add optional headers
        if region:
            headers["X-TC-Region"] = region
        if token:
            headers["X-TC-Token"] = token

        try:
            # Send request using requests library
            response = requests.post(
                url=f"https://{self._services[self.settings.get('service')]}",
                headers=headers,
                data=payload,
                timeout=10
            )

            # Raise an exception for bad responses
            response.raise_for_status()

            # Return JSON response
            return response

        except requests.RequestException as err:
            print(f"Request error: {err}")

            # If there's a response, print its content for debugging
            if hasattr(err, 'response') and err.response is not None:
                print(f"Response content: {err.response.text}")

            return None

    def json_request(self, action, payload):
        response = self.send_request(action=action, payload=payload)
        if response is None:
            syslog.syslog(syslog.LOG_ERR, "Account %s request failed [%s]" % (self.description, action))
            return None
        try:
            data = response.json()
        except requests.exceptions.JSONDecodeError:
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s error parsing JSON response [%s] %s" % (self.description, action, response.text)
            )
            return None
        if 'Response' in data and 'Error' in data['Response']:
            if action == 'DescribeRecordList' and data['Response']['Error'].get('Code') == 'ResourceNotFound.NoDataOfRecord':
                return {'Response': {'RecordList': []}}
            syslog.syslog(
                syslog.LOG_ERR,
                "Account %s Tencent Cloud DNS error [%s] %s %s" % (
                    self.description,
                    action,
                    data['Response']['Error'].get('Code', ''),
                    data['Response']['Error'].get('Message', '')
                )
            )
            return None
        return data

    def _zone(self, hostnames):
        zone = (self.settings.get('zone') or '').strip().strip('.')
        if zone:
            return zone

        for hostname in hostnames:
            name = hostname.strip().strip('.')
            if name in ('', '@'):
                continue
            if name.startswith('*.'):
                name = name[2:]
            labels = name.split('.')
            if len(labels) >= 2:
                zone = '.'.join(labels[-2:])
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    "Account %s inferred Tencent Cloud DNS zone %s from hostname %s" % (
                        self.description, zone, hostname
                    )
                )
                return zone
        return ''

    def _subdomain(self, hostname, zone):
        name = hostname.strip().strip('.')
        if name == zone or name == '@':
            return '@'
        suffix = ".%s" % zone
        if zone and name.endswith(suffix):
            return name[:-len(suffix)] or '@'
        return name

    def _ttl(self):
        try:
            ttl = int(self.settings.get('ttl') or 600)
        except (TypeError, ValueError):
            ttl = 600
        return max(ttl, 600)

    def execute(self):
        if super().execute():
            # IPv4/IPv6
            recordType = "AAAA" if str(self.current_address).find(':') > 1 else "A"

            subdomains = []
            hostnames = self.settings.get('hostnames').split(',')
            zone = self._zone(hostnames)
            if not zone:
                syslog.syslog(
                    syslog.LOG_ERR,
                    "Account %s zone is required for Tencent Cloud DNS" % self.description
                )
                return False
            for _subdomain in hostnames:
                subdomains.append(self._subdomain(_subdomain, zone))

            if len(subdomains) < 1:
                syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s hostnames format error" % self.description
                    )
                return False

            updated = []
            default_record_line = self.settings.get('resourceId') or '默认'
            ttl = self._ttl()
            for subdomain in subdomains:
                payload = self.json_request(
                    action='DescribeRecordList',
                    payload={
                        'Domain': zone,
                        'Subdomain': subdomain,
                        'RecordType': recordType
                    }
                )
                if payload is None:
                    return False

                record_id = None
                record_line = default_record_line
                for record in payload.get('Response', {}).get('RecordList', []):
                    if record.get('Name') == subdomain and record.get('Type') == recordType:
                        record_id = record.get('RecordId')
                        record_line = self.settings.get('resourceId') or record.get('Line') or default_record_line
                        break

                request_payload = {
                    'Domain': zone,
                    'RecordType': recordType,
                    'RecordLine': record_line,
                    'Value': str(self.current_address),
                    'SubDomain': subdomain,
                    'TTL': ttl
                }
                action = 'CreateRecord'
                if record_id:
                    request_payload['RecordId'] = int(record_id)
                    action = 'ModifyRecord'

                result = self.json_request(action=action, payload=request_payload)
                if result is None or not result.get('Response', {}).get('RecordId'):
                    syslog.syslog(
                        syslog.LOG_ERR,
                        "Account %s failed to set new ip %s for %s" % (
                            self.description, self.current_address, subdomain
                        )
                    )
                    return False
                updated.append(subdomain)

            syslog.syslog(
                syslog.LOG_NOTICE,
                "Account %s set new ip %s %s" % (
                    self.description,
                    self.current_address,
                    updated
                )
            )
            self.update_state(address=self.current_address)
            return True


        return False
