<?php

/*
 * Copyright (C) 2014-2026 Deciso B.V.
 * Copyright (C) 2010 Erik Fonnesbeck
 * Copyright (C) 2008-2010 Ermal Luçi
 * Copyright (C) 2004-2008 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2006 Daniel S. Haischt
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
const LOG_FILE = "/var/log/sing-box.log";
const LOG_TAIL_LINES = 200;
const LOG_MAX_BYTES = 262144;

header('Content-Type: text/plain; charset=UTF-8');

function log_t($text)
{
    $config_language = '';
    if (is_readable('/conf/config.xml')) {
        $config_xml = @file_get_contents('/conf/config.xml');
        if (is_string($config_xml) && preg_match('/<language>([^<]+)<\/language>/', $config_xml, $matches)) {
            $config_language = $matches[1];
        }
    }
    $map = [
        'zh' => [
            'Error' => '错误',
            'Log file was not found.' => '日志文件未找到！',
            'Unable to read the log file.' => '无法读取日志文件！',
            'Unable to read the log size.' => '无法读取日志大小！',
        ],
    ];
    $candidate = strtolower(str_replace('-', '_', $config_language));
    $lang = $candidate === 'zh_cn' ? 'zh' : 'en';
    return $map[$lang][$text] ?? $text;
}

function read_log_tail($log_file, $max_lines, $max_bytes)
{
    if (!is_file($log_file) || !is_readable($log_file)) {
        return "[" . log_t('Error') . "] " . log_t('Log file was not found.');
    }

    $fp = @fopen($log_file, 'rb');
    if ($fp === false) {
        return "[" . log_t('Error') . "] " . log_t('Unable to read the log file.');
    }

    $file_size = filesize($log_file);
    if ($file_size === false) {
        fclose($fp);
        return "[" . log_t('Error') . "] " . log_t('Unable to read the log size.');
    }

    $read_size = min($file_size, $max_bytes);
    if ($read_size <= 0) {
        fclose($fp);
        return "";
    }

    fseek($fp, -$read_size, SEEK_END);
    $content = fread($fp, $read_size);
    fclose($fp);

    if ($content === false || $content === '') {
        return "";
    }

    $lines = preg_split("/\r\n|\n|\r/", $content);
    if ($file_size > $read_size && !empty($lines)) {
        array_shift($lines);
    }

    return implode("\n", array_slice($lines, -$max_lines));
}

echo read_log_tail(LOG_FILE, LOG_TAIL_LINES, LOG_MAX_BYTES);
