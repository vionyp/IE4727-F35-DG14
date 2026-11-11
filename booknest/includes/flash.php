<?php
// Flash messages: short feedback that survives one redirect.

// Queues a message of type success, error or info.
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// Prints and clears all queued messages.
function flash_render(): string
{
    if (is_live_refresh()) {
        return '';   // a live update must not use up a message meant for the next page the member opens
    }
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    if (!$items) {
        return '';
    }
    $labels = ['success' => 'Done', 'error' => 'Please check', 'info' => 'Note'];
    $html = '<div class="flash-stack container">';
    foreach ($items as $f) {
        $type = in_array($f['type'], ['success', 'error', 'info'], true) ? $f['type'] : 'info';
        $role = $type === 'error' ? 'alert' : 'status';
        $html .= '<div class="flash flash-' . $type . '" role="' . $role . '">'
            . '<span class="flash-label">' . $labels[$type] . '</span>'
            . '<p>' . e($f['message']) . '</p>'
            . '<button type="button" class="flash-close" data-dismiss aria-label="Dismiss message" hidden>&times;</button>'
            . '</div>';
    }
    return $html . '</div>';
}
