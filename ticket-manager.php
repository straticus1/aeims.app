<?php
require_once 'auth_functions.php';
requireLogin();

$config = include 'config.php';
$user = getCurrentUser();
$userInfo = getUserInfo();

// Handle ticket actions
$action = $_GET['action'] ?? 'list';
$ticketId = $_GET['id'] ?? null;
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'update_status':
            $result = updateTicketStatus($_POST['ticket_id'], $_POST['status']);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
        case 'add_response':
            $result = addTicketResponse($_POST['ticket_id'], $_POST['response'], $_POST['response_type']);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
    }
}

// Get tickets based on action
$tickets = [];
$selectedTicket = null;

if ($action === 'view' && $ticketId) {
    $selectedTicket = getTicketById($ticketId);
    if (!$selectedTicket) {
        $message = 'Ticket not found';
        $messageType = 'error';
        $action = 'list';
    }
}

if ($action === 'list' || !$selectedTicket) {
    $tickets = getAllTickets();
}

function getAllTickets() {
    $allTickets = [];
    $dataDir = __DIR__ . '/data';

    // Load support tickets
    $supportFile = $dataDir . '/support_tickets.json';
    if (file_exists($supportFile)) {
        $content = file_get_contents($supportFile);
        $supportTickets = json_decode($content, true) ?: [];
        foreach ($supportTickets as $ticket) {
            $ticket['ticket_type'] = 'support';
            $allTickets[] = $ticket;
        }
    }

    // Load emergency tickets
    $emergencyFile = $dataDir . '/emergency_tickets.json';
    if (file_exists($emergencyFile)) {
        $content = file_get_contents($emergencyFile);
        $emergencyTickets = json_decode($content, true) ?: [];
        foreach ($emergencyTickets as $ticket) {
            $ticket['ticket_type'] = 'emergency';
            $allTickets[] = $ticket;
        }
    }

    // Sort by creation date (newest first)
    usort($allTickets, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    return $allTickets;
}

function getTicketById($ticketId) {
    $tickets = getAllTickets();
    foreach ($tickets as $ticket) {
        if ($ticket['id'] === $ticketId) {
            return $ticket;
        }
    }
    return null;
}

function updateTicketStatus($ticketId, $newStatus) {
    $tickets = getAllTickets();
    $ticketFound = false;
    $ticketType = null;

    // Find the ticket and determine its type
    foreach ($tickets as $ticket) {
        if ($ticket['id'] === $ticketId) {
            $ticketFound = true;
            $ticketType = $ticket['ticket_type'];
            break;
        }
    }

    if (!$ticketFound) {
        return ['success' => false, 'message' => 'Ticket not found'];
    }

    // Load the appropriate file
    $dataDir = __DIR__ . '/data';
    $filename = $dataDir . '/' . ($ticketType === 'emergency' ? 'emergency_tickets.json' : 'support_tickets.json');

    if (!file_exists($filename)) {
        return ['success' => false, 'message' => 'Ticket file not found'];
    }

    $content = file_get_contents($filename);
    $fileTickets = json_decode($content, true) ?: [];

    // Update the ticket status
    foreach ($fileTickets as &$ticket) {
        if ($ticket['id'] === $ticketId) {
            $ticket['status'] = $newStatus;
            $ticket['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }

    // Save the updated tickets
    if (file_put_contents($filename, json_encode($fileTickets, JSON_PRETTY_PRINT))) {
        return ['success' => true, 'message' => 'Ticket status updated successfully'];
    }

    return ['success' => false, 'message' => 'Failed to update ticket status'];
}

function addTicketResponse($ticketId, $response, $responseType) {
    $tickets = getAllTickets();
    $ticketFound = false;
    $ticketType = null;

    // Find the ticket and determine its type
    foreach ($tickets as $ticket) {
        if ($ticket['id'] === $ticketId) {
            $ticketFound = true;
            $ticketType = $ticket['ticket_type'];
            break;
        }
    }

    if (!$ticketFound) {
        return ['success' => false, 'message' => 'Ticket not found'];
    }

    // Load the appropriate file
    $dataDir = __DIR__ . '/data';
    $filename = $dataDir . '/' . ($ticketType === 'emergency' ? 'emergency_tickets.json' : 'support_tickets.json');

    if (!file_exists($filename)) {
        return ['success' => false, 'message' => 'Ticket file not found'];
    }

    $content = file_get_contents($filename);
    $fileTickets = json_decode($content, true) ?: [];

    // Add response to the ticket
    foreach ($fileTickets as &$ticket) {
        if ($ticket['id'] === $ticketId) {
            if (!isset($ticket['responses'])) {
                $ticket['responses'] = [];
            }

            $ticket['responses'][] = [
                'id' => uniqid(),
                'response' => $response,
                'type' => $responseType,
                'author' => getCurrentUser(),
                'author_name' => getUserInfo()['name'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $ticket['updated_at'] = date('Y-m-d H:i:s');

            // Auto-update status based on response type
            if ($responseType === 'staff_response') {
                $ticket['status'] = 'awaiting_customer';
            } elseif ($responseType === 'customer_response') {
                $ticket['status'] = 'awaiting_staff';
            }

            break;
        }
    }

    // Save the updated tickets
    if (file_put_contents($filename, json_encode($fileTickets, JSON_PRETTY_PRINT))) {
        return ['success' => true, 'message' => 'Response added successfully'];
    }

    return ['success' => false, 'message' => 'Failed to add response'];
}

function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'open':
        case 'emergency':
            return 'status-open';
        case 'in_progress':
        case 'awaiting_staff':
            return 'status-progress';
        case 'awaiting_customer':
            return 'status-waiting';
        case 'resolved':
        case 'closed':
            return 'status-resolved';
        default:
            return 'status-default';
    }
}

function getPriorityBadgeClass($priority) {
    switch (strtolower($priority)) {
        case 'critical':
            return 'priority-critical';
        case 'high':
            return 'priority-high';
        case 'medium':
            return 'priority-medium';
        case 'low':
            return 'priority-low';
        default:
            return 'priority-default';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Management - <?php echo $config['site']['name']; ?> Customer Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Ticket-specific styles */
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .ticket-table th,
        .ticket-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .ticket-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }

        .ticket-table tr:hover {
            background: #f8fafc;
        }

        .ticket-id {
            font-family: monospace;
            font-weight: 600;
            color: #3b82f6;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-open { background: #fef2f2; color: #dc2626; }
        .status-progress { background: #fffbeb; color: #d97706; }
        .status-waiting { background: #eff6ff; color: #2563eb; }
        .status-resolved { background: #ecfdf5; color: #059669; }
        .status-default { background: #f1f5f9; color: #64748b; }

        .priority-critical { background: #fef2f2; color: #dc2626; }
        .priority-high { background: #fff7ed; color: #ea580c; }
        .priority-medium { background: #fffbeb; color: #d97706; }
        .priority-low { background: #f0f9ff; color: #0369a1; }
        .priority-default { background: #f1f5f9; color: #64748b; }

        .ticket-detail {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .ticket-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .meta-value {
            font-size: 14px;
            color: #374151;
        }

        .ticket-content {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .ticket-responses {
            margin-top: 24px;
        }

        .response-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .response-author {
            font-weight: 600;
            color: #374151;
        }

        .response-date {
            font-size: 12px;
            color: #64748b;
        }

        .response-type {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .response-type.staff { background: #eff6ff; color: #2563eb; }
        .response-type.customer { background: #f0fdf4; color: #059669; }

        .response-form {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body class="dashboard-body">
    <!-- Top Navigation -->
    <header class="top-nav">
        <div class="nav-brand">
            <a href="index.php" class="logo-link">
                <h1 class="nav-logo"><?php echo $config['site']['name']; ?></h1>
                <span class="nav-subtitle">Customer Portal</span>
            </a>
        </div>

        <nav class="main-nav">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Configuration</span>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="setup.php">Initial Setup</a></li>
                        <li><a href="domains.php">Domain Management</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Analytics</span>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="analytics.php">View Analytics</a></li>
                        <li><a href="reports.php">Reports</a></li>
                        <li><a href="exports.php">Data Export</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle active">
                        <span class="nav-icon">🎫</span>
                        <span class="nav-text">Support</span>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="ticket-manager.php" class="active">Support Tickets</a></li>
                        <li><a href="support.php">Contact Support</a></li>
                        <li><a href="support.php#knowledge-base">Knowledge Base</a></li>
                    </ul>
                </li>

                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a href="admin-dashboard.php" class="nav-link admin-link">
                        <span class="nav-icon">👑</span>
                        <span class="nav-text">Admin Panel</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="user-menu">
            <div class="user-dropdown">
                <button class="user-btn">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($userInfo['name'], 0, 2)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($userInfo['name']); ?></div>
                        <div class="user-type"><?php echo ucfirst($userInfo['type']); ?></div>
                    </div>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <ul class="user-dropdown-menu">
                    <li><a href="profile.php">Profile Settings</a></li>
                    <li><a href="security.php">Security</a></li>
                    <li><a href="?logout=1">Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <main class="dashboard-main">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 20px;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'view' && $selectedTicket): ?>
                <!-- Ticket Detail View -->
                <header class="dashboard-header">
                    <div class="header-content">
                        <h1>Ticket Details</h1>
                        <p>Ticket ID: <?php echo htmlspecialchars($selectedTicket['id']); ?></p>
                    </div>
                    <div class="header-actions">
                        <a href="ticket-manager.php" class="btn btn-secondary">← Back to List</a>
                        <a href="support.php#ticket-form" class="btn btn-primary">Create New Ticket</a>
                    </div>
                </header>

                <div class="ticket-detail">
                    <div class="ticket-header">
                        <div>
                            <h2><?php echo htmlspecialchars($selectedTicket['subject'] ?? 'Emergency Support Request'); ?></h2>
                            <p class="ticket-id">ID: <?php echo htmlspecialchars($selectedTicket['id']); ?></p>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span class="status-badge <?php echo getStatusBadgeClass($selectedTicket['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $selectedTicket['status'])); ?>
                            </span>
                            <?php if (isset($selectedTicket['priority'])): ?>
                            <span class="status-badge <?php echo getPriorityBadgeClass($selectedTicket['priority']); ?>">
                                <?php echo ucfirst($selectedTicket['priority']); ?> Priority
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ticket-meta">
                        <div class="meta-item">
                            <span class="meta-label">Submitted By</span>
                            <span class="meta-value"><?php echo htmlspecialchars($selectedTicket['name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Email</span>
                            <span class="meta-value"><?php echo htmlspecialchars($selectedTicket['email']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Created</span>
                            <span class="meta-value"><?php echo date('M j, Y g:i A', strtotime($selectedTicket['created_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Type</span>
                            <span class="meta-value"><?php echo ucfirst($selectedTicket['ticket_type']); ?> Ticket</span>
                        </div>
                        <?php if (isset($selectedTicket['category'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Category</span>
                            <span class="meta-value"><?php echo ucfirst($selectedTicket['category']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($selectedTicket['updated_at'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Last Updated</span>
                            <span class="meta-value"><?php echo date('M j, Y g:i A', strtotime($selectedTicket['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="ticket-content">
                        <h4>Description:</h4>
                        <p><?php echo nl2br(htmlspecialchars($selectedTicket['description'])); ?></p>

                        <?php if (isset($selectedTicket['environment']) && !empty($selectedTicket['environment'])): ?>
                        <h4 style="margin-top: 16px;">Environment:</h4>
                        <p><?php echo nl2br(htmlspecialchars($selectedTicket['environment'])); ?></p>
                        <?php endif; ?>

                        <?php if (isset($selectedTicket['impact']) && !empty($selectedTicket['impact'])): ?>
                        <h4 style="margin-top: 16px;">Business Impact:</h4>
                        <p><?php echo nl2br(htmlspecialchars($selectedTicket['impact'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Responses Section -->
                    <?php if (isset($selectedTicket['responses']) && !empty($selectedTicket['responses'])): ?>
                    <div class="ticket-responses">
                        <h3>Responses</h3>
                        <?php foreach ($selectedTicket['responses'] as $response): ?>
                        <div class="response-item">
                            <div class="response-header">
                                <div>
                                    <span class="response-author"><?php echo htmlspecialchars($response['author_name']); ?></span>
                                    <span class="response-type <?php echo $response['type'] === 'staff_response' ? 'staff' : 'customer'; ?>">
                                        <?php echo $response['type'] === 'staff_response' ? 'Staff' : 'Customer'; ?>
                                    </span>
                                </div>
                                <span class="response-date"><?php echo date('M j, Y g:i A', strtotime($response['created_at'])); ?></span>
                            </div>
                            <div class="response-content">
                                <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Add Response Form -->
                    <div class="response-form">
                        <h4>Add Response</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_response">
                            <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($selectedTicket['id']); ?>">

                            <div class="form-group">
                                <label for="response_type">Response Type</label>
                                <select name="response_type" id="response_type" required>
                                    <option value="customer_response">Customer Response</option>
                                    <?php if (isAdmin()): ?>
                                    <option value="staff_response">Staff Response</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="response">Response</label>
                                <textarea name="response" id="response" required placeholder="Type your response here..."></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Add Response</button>
                            </div>
                        </form>
                    </div>

                    <!-- Status Update Form (Admin Only) -->
                    <?php if (isAdmin()): ?>
                    <div class="response-form">
                        <h4>Update Status</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($selectedTicket['id']); ?>">

                            <div class="form-group">
                                <label for="status">New Status</label>
                                <select name="status" id="status" required>
                                    <option value="open" <?php echo $selectedTicket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $selectedTicket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="awaiting_customer" <?php echo $selectedTicket['status'] === 'awaiting_customer' ? 'selected' : ''; ?>>Awaiting Customer</option>
                                    <option value="awaiting_staff" <?php echo $selectedTicket['status'] === 'awaiting_staff' ? 'selected' : ''; ?>>Awaiting Staff</option>
                                    <option value="resolved" <?php echo $selectedTicket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo $selectedTicket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-secondary">Update Status</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Ticket List View -->
                <header class="dashboard-header">
                    <div class="header-content">
                        <h1>Support Tickets</h1>
                        <p>Manage and track your support requests</p>
                    </div>
                    <div class="header-actions">
                        <a href="support.php#ticket-form" class="btn btn-primary">Create New Ticket</a>
                        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                </header>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>All Tickets</h3>
                        <span><?php echo count($tickets); ?> ticket(s)</span>
                    </div>
                    <div class="card-content">
                        <?php if (empty($tickets)): ?>
                        <div class="empty-state">
                            <span class="empty-icon">🎫</span>
                            <h4>No tickets found</h4>
                            <p>You haven't submitted any support tickets yet.</p>
                            <a href="support.php#ticket-form" class="btn btn-primary">Create Your First Ticket</a>
                        </div>
                        <?php else: ?>
                        <table class="ticket-table">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><span class="ticket-id"><?php echo htmlspecialchars($ticket['id']); ?></span></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ticket['subject'] ?? 'Emergency Support Request'); ?></strong>
                                        <br>
                                        <small style="color: #64748b;"><?php echo htmlspecialchars($ticket['name']); ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $ticket['ticket_type'] === 'emergency' ? 'priority-critical' : 'status-default'; ?>">
                                            <?php echo ucfirst($ticket['ticket_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($ticket['priority'])): ?>
                                        <span class="status-badge <?php echo getPriorityBadgeClass($ticket['priority']); ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="status-badge priority-critical">Critical</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusBadgeClass($ticket['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                    <td><?php echo isset($ticket['updated_at']) ? date('M j, Y', strtotime($ticket['updated_at'])) : '-'; ?></td>
                                    <td>
                                        <a href="?action=view&id=<?php echo urlencode($ticket['id']); ?>" class="btn btn-outline btn-sm">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>