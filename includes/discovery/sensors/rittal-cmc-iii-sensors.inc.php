<?php

/**
 * rittal-cmc-iii-sensors.inc.php
 *
 * LibreNMS sensors discovery module for Rittal CMC III
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link      https://www.librenms.org
 *
 * @copyright 2020 Denny Friebe
 * @author    Denny Friebe <denny.friebe@icera-network.de>
 */

use LibreNMS\Util\Number;
use LibreNMS\Util\StringHelpers;

$cmc_iii_var_table = snmpwalk_cache_oid($device, 'cmcIIIVarTable', [], 'RITTAL-CMC-III-MIB', null);
$cmc_iii_sensors = [];
$last_index_prefix = $current_index_prefix = '';
$unique_desc_counter = [];

foreach ($cmc_iii_var_table as $index => $entry) {
    $var_name_parts = explode('.', $entry['cmcIIIVarName']);
    array_pop($var_name_parts);
    $sensor_name = implode(' ', $var_name_parts);
    $var_type = $entry['cmcIIIVarType'];
    $sensor_id = count($cmc_iii_sensors);

    $index_r = explode('.', $index);
    if (count($index_r) > 1) {
        $current_index_prefix = $index_r[0];
    }

    if ((! isset($cmc_iii_sensors[$sensor_id]['name']) || $cmc_iii_sensors[$sensor_id]['name'] != $sensor_name) || $last_index_prefix != $current_index_prefix) {
        if ($sensor_id == 0) {
            $sensor_id = 1;
        } else {
            $sensor_id++;
        }

        if (str_contains($sensor_name, $entry['cmcIIIVarValueStr'])) {
            $sensor_desc = $entry['cmcIIIVarValueStr'];
        } else {
            $sensor_desc = "$sensor_name {$entry['cmcIIIVarValueStr']}";
        }

        $cmc_iii_sensors[$sensor_id]['name'] = $sensor_name;
        $cmc_iii_sensors[$sensor_id]['desc'] = $sensor_desc;

        // count descriptions => used to generate unique description count suffix later
        if (! isset($unique_desc_counter[$sensor_desc])) {
            $unique_desc_counter[$sensor_desc] = [];
        }
        array_push($unique_desc_counter[$sensor_desc], $sensor_id);

        $last_index_prefix = $current_index_prefix;
    }

    switch ($var_type) {
        case 'setHigh':
            $cmc_iii_sensors[$sensor_id]['high_limit'] = $entry['cmcIIIVarValueInt'];
            break;
        case 'setWarn':
            $cmc_iii_sensors[$sensor_id]['warn_limit'] = $entry['cmcIIIVarValueInt'];
            break;
        case 'setWarnLow':
            $cmc_iii_sensors[$sensor_id]['low_warn_limit'] = $entry['cmcIIIVarValueInt'];
            break;
        case 'setLow':
            $cmc_iii_sensors[$sensor_id]['low_limit'] = $entry['cmcIIIVarValueInt'];
            break;
        case 'logic':
            $sensor_logic = explode(' / ', $entry['cmcIIIVarValueStr']);
            $cmc_iii_sensors[$sensor_id]['logic'][0] = substr($sensor_logic[0], 2);
            $cmc_iii_sensors[$sensor_id]['logic'][1] = substr($sensor_logic[1], 2);
            break;
        case 'value':
            $cmc_iii_sensors[$sensor_id]['oid'] = '.1.3.6.1.4.1.2606.7.4.2.2.1.11.' . $index;

            if (! empty($entry['cmcIIIVarValueInt'])) {
                $cmc_iii_sensors[$sensor_id]['value'] = Number::cast($entry['cmcIIIVarValueInt']);
            } else {
                $cmc_iii_sensors[$sensor_id]['value'] = Number::cast($entry['cmcIIIVarValueStr']);
            }

            if ($entry['cmcIIIVarScale'][0] == '-') {
                $cmc_iii_sensors[$sensor_id]['divisor'] = substr($entry['cmcIIIVarScale'], 1);
            } elseif ($entry['cmcIIIVarScale'][0] == '+') {
                $cmc_iii_sensors[$sensor_id]['multiplier'] = substr($entry['cmcIIIVarScale'], 1);
            }

            // encode string to ensure that degree sign may be used properly for unit comparison
            $unit = StringHelpers::inferEncoding($entry['cmcIIIVarUnit']);
            $type = 'state';
            $temperature_units = ['degree C', 'degree F', '°C', '°F'];
            if ($unit == 'mA') {
                //In some cases we get a mA value. However, the cmcIIIVarScale is simply 1.
                //Therefore, we must hardcode the divisor here to calculate the value into A.
                $type = 'current';
                $cmc_iii_sensors[$sensor_id]['divisor'] = 1000;
            } elseif ($unit == 'A') {
                $type = 'current';
            } elseif ($unit == 'Wh' || $unit == 'VAh') {
                $cmc_iii_sensors[$sensor_id]['divisor'] = 1000;
                $type = 'power_consumed';
            } elseif ($unit == 'kWh' || $unit == 'kVAh') {
                $type = 'power_consumed';
            } elseif ($unit == 'Hz') {
                $type = 'frequency';
            } elseif (in_array($unit, $temperature_units)) {
                $type = 'temperature';
            } elseif ($unit == 'l/min') {
                $type = 'waterflow';
            } elseif ($unit == 'V') {
                $type = 'voltage';
            } elseif ($unit == 'W' || $unit == 'VA' || $unit == 'var') {
                $type = 'power';
            } elseif ($unit == '%') {
                $type = 'percent';
            }
            $cmc_iii_sensors[$sensor_id]['type'] = $type;
            break;
    }
}

// generate unique sensor descriptions
foreach ($unique_desc_counter as $sensor_desc => $sensor_id_r) {
    if (count($sensor_id_r) < 2) {
        continue;
    }

    for ($index = 0; $index < count($sensor_id_r); $index++) {
        $cmc_iii_sensors[$sensor_id_r[$index]]['desc'] .= ' ' . str_pad($index + 1, 2, 0, STR_PAD_LEFT);
    }
}

//At first device discovery the serial number is not set. But we need this in the next step for our state indexes.
if (! $device['serial']) {
    $serial_number = snmp_get($device, 'cmcIIIUnitSerial.0', '-Oqv', 'RITTAL-CMC-III-MIB');
} else {
    $serial_number = $device['serial'];
}

foreach ($cmc_iii_sensors as $sensor_id => $sensor_data) {
    // Some sensors provide either no useful data at all or only partially useful data.
    if (! isset($sensor_data['oid'])
    || $sensor_data['name'] == 'System V24 Port'
    || $sensor_data['name'] == 'Memory USB-Stick'
    || $sensor_data['name'] == 'Memory SD-Card'
    || $sensor_data['name'] == 'Login'
    || preg_match('/(Power Factor)|(Runtime)/', $sensor_data['name'])) {
        echo "\n" . $sensor_data['name'] . " skipped!\n";
        continue;
    }

    // No logic is provided for the sensor types 'Smoke' and 'Access'.
    if ($sensor_data['name'] == 'Smoke') {
        $sensor_data['logic'][0] = 'OK';
        $sensor_data['logic'][1] = 'Alarm';
    } elseif ($sensor_data['name'] == 'Access') {
        $sensor_data['logic'][0] = 'Closed';
        $sensor_data['logic'][1] = 'Open';
    }

    if (isset($sensor_data['logic'])) {
        // We need separate state indexes for each device because the sensor logic can vary from device to device depending on its configuration. So we add our device serial here.
        $sensor_data['name'] = $sensor_data['name'] . '_' . $serial_number;
        $sensor_logic = [
            [
                'value' => 0,
                'generic' => 0,
                'graph' => 1,
                'descr' => $sensor_data['logic'][0],
            ],
            [
                'value' => 1,
                'generic' => 0,
                'graph' => 1,
                'descr' => $sensor_data['logic'][1],
            ],
        ];

        create_state_index($sensor_data['name'], $sensor_logic);
    }

    if (isset($sensor_data['divisor'])) {
        if (isset($sensor_data['low_limit'])) {
            $sensor_data['low_limit'] = ($sensor_data['low_limit'] / $sensor_data['divisor']);
        }
        if (isset($sensor_data['low_warn_limit'])) {
            $sensor_data['low_warn_limit'] = ($sensor_data['low_warn_limit'] / $sensor_data['divisor']);
        }
        if (isset($sensor_data['warn_limit'])) {
            $sensor_data['warn_limit'] = ($sensor_data['warn_limit'] / $sensor_data['divisor']);
        }
        if (isset($sensor_data['high_limit'])) {
            $sensor_data['high_limit'] = ($sensor_data['high_limit'] / $sensor_data['divisor']);
        }

        $sensor_data['value'] = ($sensor_data['value'] / $sensor_data['divisor']);
    } elseif (isset($sensor_data['multiplier'])) {
        if (isset($sensor_data['low_limit'])) {
            $sensor_data['low_limit'] = ($sensor_data['low_limit'] * $sensor_data['multiplier']);
        }
        if (isset($sensor_data['low_warn_limit'])) {
            $sensor_data['low_warn_limit'] = ($sensor_data['low_warn_limit'] * $sensor_data['multiplier']);
        }
        if (isset($sensor_data['warn_limit'])) {
            $sensor_data['warn_limit'] = ($sensor_data['warn_limit'] * $sensor_data['multiplier']);
        }
        if (isset($sensor_data['high_limit'])) {
            $sensor_data['high_limit'] = ($sensor_data['high_limit'] * $sensor_data['multiplier']);
        }

        $sensor_data['value'] = ($sensor_data['value'] * $sensor_data['multiplier']);
    }
    discover_sensor(null, $sensor_data['type'], $device, $sensor_data['oid'], $sensor_id, $sensor_data['name'], $sensor_data['desc'], $sensor_data['divisor'] ?? 1, $sensor_data['multiplier'] ?? 1, $sensor_data['low_limit'] ?? null, $sensor_data['low_warn_limit'] ?? null, $sensor_data['warn_limit'] ?? null, $sensor_data['high_limit'] ?? null, $sensor_data['value']);
}

unset($cmc_iii_var_table, $cmc_iii_sensors, $last_index_prefix, $current_index_prefix, $unique_desc_counter, $index, $entry, $var_name_parts, $sensor_name, $sensor_desc, $var_type, $sensor_id, $sensor_logic, $unit, $type, $sensor_data, $serial_number);
