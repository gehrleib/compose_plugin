<?php
/**
 * Compose List Functions for Compose Manager
 * 
 * Contains helper functions used by compose_list.php for generating the stack list UI.
 * Separated from compose_list.php to allow unit testing without triggering the page render.
 */

/**
 * Create a combo button with dropdown menu.
 * 
 * @param string $text The button text
 * @param string $id The base ID for the button elements
 * @param string $onClick The JavaScript function to call on click
 * @param string $onClickParams Parameters to pass to the onClick function
 * @param array $items Array of dropdown menu items
 * @return string The HTML for the combo button
 */
function createComboButton($text, $id, $onClick, $onClickParams, $items) {
    $o = "";
    $o .= "<div class='combo-btn-group'>";
    $o .= "<input type='button' value='$text' class='combo-btn-group-left' id='$id-left-btn' onclick='$onClick($onClickParams);'>";
    $o .= "<section class='combo-btn-subgroup dropdown'>";
    $o .= "<button type='button' class='dropdown-toggle combo-btn-group-right' data-toggle='dropdown'><i class='fa fa-caret-down'></i></button>";
    $o .= "<div class='dropdown-content'>";
    foreach ($items as $item) {
        $o .= "<a href='#' onclick='$onClick($onClickParams, &quot;$item&quot;);'>$item</a>";
    }
    $o .= "</div>";
    $o .= "</section>";
    $o .= "</div>";
    return $o;
}

/**
 * Get the element ID from a project name.
 * Replaces dots with dashes and removes spaces.
 *
 * @param string $project The project name
 * @return string The sanitized element ID
 */
function getProjectElementId($project) {
    $id = str_replace(".", "-", $project);
    $id = str_replace(" ", "", $id);
    return $id;
}

/**
 * Parse container labels to extract compose project name.
 *
 * @param string $labels The labels string from docker inspect
 * @return string|null The project name or null if not found
 */
function extractProjectFromLabels($labels) {
    if (preg_match('/com\.docker\.compose\.project=([^,]+)/', $labels, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Calculate human-readable uptime from a timestamp.
 *
 * @param string $startedAt The start timestamp
 * @return string Human-readable uptime string
 */
function calculateUptime($startedAt) {
    if (!$startedAt) {
        return '';
    }
    
    $startTime = strtotime($startedAt);
    if (!$startTime) {
        return '';
    }
    
    $diffSecs = time() - $startTime;
    $mins = floor($diffSecs / 60);
    $hours = floor($diffSecs / 3600);
    $days = floor($diffSecs / 86400);
    $weeks = floor($days / 7);
    $months = floor($days / 30);
    $years = floor($days / 365);
    
    if ($mins < 120) {
        return "Uptime: " . $mins . " min" . ($mins !== 1 ? "s" : "");
    } elseif ($hours < 48) {
        return "Uptime: " . $hours . " hour" . ($hours !== 1 ? "s" : "");
    } elseif ($days < 14) {
        return "Uptime: " . $days . " day" . ($days !== 1 ? "s" : "");
    } elseif ($weeks < 8) {
        return "Uptime: " . $weeks . " week" . ($weeks !== 1 ? "s" : "");
    } elseif ($months < 24) {
        return "Uptime: " . $months . " month" . ($months !== 1 ? "s" : "");
    } else {
        return "Uptime: " . $years . " year" . ($years !== 1 ? "s" : "");
    }
}

/**
 * Determine status text and class for a stack based on container states.
 *
 * @param bool $isUp Whether any containers exist
 * @param bool $isRunning Whether any containers are running
 * @param bool $isExited Whether any containers are exited
 * @param bool $isPaused Whether any containers are paused
 * @param bool $isRestarting Whether any containers are restarting
 * @return array ['text' => string, 'class' => string]
 */
function getStackStatus($isUp, $isRunning, $isExited, $isPaused, $isRestarting) {
    $statusText = "Stopped";
    $statusClass = "status-stopped";
    
    if ($isUp) {
        if ($isExited && !$isRunning) {
            $statusText = "Exited";
            $statusClass = "status-exited";
        } elseif ($isRunning && !$isExited && !$isPaused && !$isRestarting) {
            $statusText = "Running";
            $statusClass = "status-running";
        } elseif ($isPaused && !$isExited && !$isRunning && !$isRestarting) {
            $statusText = "Paused";
            $statusClass = "status-paused";
        } elseif ($isPaused && !$isExited) {
            $statusText = "Partial";
            $statusClass = "status-partial";
        } elseif ($isRestarting) {
            $statusText = "Restarting";
            $statusClass = "status-restarting";
        } else {
            $statusText = "Mixed";
            $statusClass = "status-mixed";
        }
    }
    
    return ['text' => $statusText, 'class' => $statusClass];
}
