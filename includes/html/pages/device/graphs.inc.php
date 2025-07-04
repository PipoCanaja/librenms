<?php

// Graphs are printed in the order they exist in \App\Facades\LibrenmsConfig::get('graph_types')
$link_array = [
    'page' => 'device',
    'device' => $device['device_id'],
    'tab' => 'graphs',
];

$bg = '#ffffff';

echo '<div style="clear: both;">';

print_optionbar_start();

echo "<span style='font-weight: bold;'>Graphs</span> &#187; ";

$graph_enable = [];
foreach (dbFetchRows('SELECT * FROM device_graphs WHERE device_id = ? ORDER BY graph', [$device['device_id']]) as $graph) {
    $section = \App\Facades\LibrenmsConfig::get("graph_types.device.{$graph['graph']}.section");
    if ($section != '') {
        $graph_enable[$section][$graph['graph']] = $graph['graph'];
    }
}

$sep = '';
foreach ($graph_enable as $section => $nothing) {
    if (isset($graph_enable) && is_array($graph_enable[$section])) {
        $type = strtolower($section);
        if (empty($vars['group'])) {
            $vars['group'] = $type;
        }

        echo $sep;
        if ($vars['group'] == $type) {
            echo '<span class="pagemenu-selected">';
        }

        if ($type == 'customoid') {
            echo generate_link(ucwords('Custom OID'), $link_array, ['group' => $type]);
        } else {
            echo generate_link(ucwords($type), $link_array, ['group' => $type]);
        }
        if ($vars['group'] == $type) {
            echo '</span>';
        }

        $sep = ' | ';
    }
}

unset($sep);

print_optionbar_end();

$group = $vars['group'] ?? array_key_first($graph_enable);
$graph_enable = $graph_enable[$group] ?? [];

if (($group != 'customoid') && is_file("includes/html/pages/device/graphs/$group.inc.php")) {
    include "includes/html/pages/device/graphs/$group.inc.php";
} else {
    foreach ($graph_enable as $graph => $entry) {
        $graph_array = [];
        if ($graph_enable[$graph]) {
            if ($graph == 'customoid') {
                foreach (dbFetchRows('SELECT * FROM `customoids` WHERE `device_id` = ? ORDER BY `customoid_descr`', [$device['device_id']]) as $graph_entry) {
                    $graph_title = \App\Facades\LibrenmsConfig::get("graph_types.device.$graph.descr") . ': ' . $graph_entry['customoid_descr'];
                    $graph_array['type'] = 'customoid_' . $graph_entry['customoid_descr'];
                    if (! empty($graph_entry['customoid_unit'])) {
                        $graph_array['unit'] = $graph_entry['customoid_unit'];
                    } else {
                        $graph_array['unit'] = 'value';
                    }
                    include 'includes/html/print-device-graph.php';
                }
            } else {
                $graph_title = \App\Facades\LibrenmsConfig::get("graph_types.device.$graph.descr");
                $graph_array['type'] = 'device_' . $graph;
                include 'includes/html/print-device-graph.php';
            }
        }
    }
}
$pagetitle[] = 'Graphs';
