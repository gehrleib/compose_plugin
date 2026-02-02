<?PHP
/**
 * AJAX endpoint for dashboard tile - returns compose stacks data
 * Called asynchronously to avoid blocking dashboard load
 */

$plugin_root = "/usr/local/emhttp/plugins/compose.manager";
$compose_root = "/boot/config/plugins/compose.manager/projects";

require_once("$plugin_root/php/defines.php");
require_once("$plugin_root/php/util.php");

$summary = [
    'total' => 0,
    'started' => 0,
    'stopped' => 0,
    'partial' => 0,
    'stacks' => []
];

if (!is_dir($compose_root)) {
    header('Content-Type: application/json');
    echo json_encode($summary);
    exit;
}

$projects = @array_diff(@scandir($compose_root), ['.', '..']);
if (!is_array($projects)) {
    header('Content-Type: application/json');
    echo json_encode($summary);
    exit;
}

// Get all compose containers (quick docker ps with labels)
$containersOutput = shell_exec("docker ps -a --format '{{json .}}' 2>/dev/null");
$containersByProject = [];
if ($containersOutput) {
    $lines = explode("\n", trim($containersOutput));
    foreach ($lines as $line) {
        if (!empty($line)) {
            $container = @json_decode($line, true);
            if ($container && isset($container['Labels'])) {
                // Parse labels to find compose project
                if (preg_match('/com\.docker\.compose\.project=([^,]+)/', $container['Labels'], $matches)) {
                    $projectName = $matches[1];
                    if (!isset($containersByProject[$projectName])) {
                        $containersByProject[$projectName] = [];
                    }
                    $containersByProject[$projectName][] = $container;
                }
            }
        }
    }
}

foreach ($projects as $project) {
    if (!is_file("$compose_root/$project/docker-compose.yml") && 
        !is_file("$compose_root/$project/indirect")) {
        continue;
    }
    
    $summary['total']++;
    
    $projectName = $project;
    if (is_file("$compose_root/$project/name")) {
        $projectName = trim(file_get_contents("$compose_root/$project/name"));
    }
    
    $sanitizedName = sanitizeStr($projectName);
    $projectContainers = $containersByProject[$sanitizedName] ?? [];
    
    $runningCount = 0;
    $totalContainers = count($projectContainers);
    
    foreach ($projectContainers as $ct) {
        if (($ct['State'] ?? '') === 'running') {
            $runningCount++;
        }
    }
    
    $state = 'stopped';
    if ($totalContainers > 0) {
        if ($runningCount === $totalContainers) {
            $state = 'started';
            $summary['started']++;
        } elseif ($runningCount > 0) {
            $state = 'partial';
            $summary['partial']++;
        } else {
            $summary['stopped']++;
        }
    } else {
        $summary['stopped']++;
    }
    
    // Check for custom project icon (URL-based via icon_url file)
    $icon = '';
    if (is_file("$compose_root/$project/icon_url")) {
        $iconUrl = trim(@file_get_contents("$compose_root/$project/icon_url"));
        if (filter_var($iconUrl, FILTER_VALIDATE_URL) && (strpos($iconUrl, 'http://') === 0 || strpos($iconUrl, 'https://') === 0)) {
            $icon = $iconUrl;
        }
    }
    
    $summary['stacks'][] = [
        'name' => $projectName,
        'folder' => $project,
        'state' => $state,
        'running' => $runningCount,
        'total' => $totalContainers,
        'icon' => $icon
    ];
}

header('Content-Type: application/json');
echo json_encode($summary);
?>
