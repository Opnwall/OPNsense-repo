<?php

namespace OPNsense\Unboundcustom\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiControllerBase
{
    public function applyAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => gettext('POST required')];
        }

        $backend = new Backend();
        $response = trim($backend->configdRun('unboundcustom apply'));
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return ['status' => 'failed', 'message' => $response ?: gettext('No response from configd')];
        }

        $messages = [
            'busy' => gettext('Another apply operation is already running.'),
            'template_failed' => gettext('Template generation failed:'),
            'stage_failed' => gettext('Could not stage the generated configuration fragment:'),
            'validation_failed' => gettext('Unbound configuration validation failed:'),
            'restart_failed' => gettext('The configuration is valid, but Unbound could not be restarted:'),
            'success' => gettext('Custom options were validated and Unbound was restarted successfully.'),
        ];
        $code = $decoded['code'] ?? '';
        $message = $messages[$code] ?? gettext('Unknown apply result.');
        if (!empty($decoded['detail'])) {
            $message .= "\n" . $decoded['detail'];
        }

        return ['status' => $decoded['status'] ?? 'failed', 'message' => $message];
    }
}
