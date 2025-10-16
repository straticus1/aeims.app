<!-- Call Button Component -->
<?php
// Required variables:
// $operator - operator array with id, display_name, metadata
// $customer - customer array with id, balance
// $domain - current site domain

$operatorId = $operator['id'];
$operatorName = $operator['display_name'] ?? $operator['username'];
$metadata = is_string($operator['metadata'] ?? '') ?
    json_decode($operator['metadata'], true) :
    ($operator['metadata'] ?? []);

$ratePerMinute = $metadata['rate_per_minute'] ?? 3.99;
$connectFee = $metadata['connect_fee'] ?? 0.99;
?>

<style>
.call-module {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
    border: 2px solid rgba(139, 92, 246, 0.3);
    border-radius: 16px;
    padding: 24px;
    margin: 24px 0;
}

.operator-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 1.1rem;
    font-weight: 600;
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.status-indicator.online { background: #22c55e; }
.status-indicator.busy { background: #f59e0b; }
.status-indicator.offline { background: #ef4444; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.balance-info {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}

.balance-info h3 {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.balance-info p {
    margin: 6px 0;
    font-size: 1rem;
}

.balance-amount {
    font-size: 1.5rem !important;
    font-weight: 700;
    color: #22c55e;
}

.free-minutes {
    color: #a78bfa;
    font-weight: 600;
}

.talk-time {
    color: #60a5fa;
    font-weight: 600;
}

.call-pricing {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 16px;
}

.call-pricing p {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.8);
}

.call-actions {
    display: flex;
    gap: 12px;
}

.add-funds-btn, .call-btn {
    flex: 1;
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.add-funds-btn {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.add-funds-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.call-btn {
    background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
}

.call-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.6);
}

.call-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.active-call {
    background: rgba(34, 197, 94, 0.1);
    border: 2px solid #22c55e;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.call-timer {
    font-size: 2.5rem;
    font-weight: 700;
    color: #22c55e;
    margin-bottom: 8px;
    font-variant-numeric: tabular-nums;
}

.call-cost {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 16px;
}

.hangup-btn {
    background: #ef4444;
    color: white;
    border: none;
    padding: 12px 32px;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.hangup-btn:hover {
    background: #dc2626;
    transform: scale(1.05);
}

.insufficient-balance {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid #ef4444;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    margin-bottom: 16px;
}

.insufficient-balance p {
    color: #fca5a5;
    margin-bottom: 12px;
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}
</style>

<div class="call-module" id="callModule" data-operator-id="<?= $operatorId ?>" data-rate="<?= $ratePerMinute ?>" data-connect-fee="<?= $connectFee ?>">
    <div class="operator-status">
        <span class="status-indicator online"></span>
        <span><?= htmlspecialchars($operatorName) ?> is Online</span>
    </div>

    <div class="balance-info" id="balanceInfo">
        <h3>Your Account</h3>
        <p class="balance-amount">Loading...</p>
        <p class="free-minutes" id="freeMinutes"></p>
        <p class="talk-time" id="talkTime"></p>
    </div>

    <div class="call-pricing">
        <p>Rate: $<?= number_format($ratePerMinute, 2) ?>/min</p>
        <p>Connect Fee: $<?= number_format($connectFee, 2) ?></p>
    </div>

    <div class="call-actions" id="callActions">
        <button class="add-funds-btn" onclick="window.location.href='/payment.php'">
            💳 Add Funds
        </button>
        <button class="call-btn" id="callBtn" onclick="initiateCall()">
            📞 Call <?= htmlspecialchars($operatorName) ?>
        </button>
    </div>

    <div class="active-call" id="activeCall" style="display:none;">
        <div class="call-timer" id="callTimer">00:00</div>
        <div class="call-cost" id="callCost">Cost: $0.00</div>
        <button class="hangup-btn" onclick="hangupCall()">Hang Up</button>
    </div>
</div>

<script>
(function() {
    const module = document.getElementById('callModule');
    const operatorId = module.dataset.operatorId;
    const ratePerMinute = parseFloat(module.dataset.rate);
    const connectFee = parseFloat(module.dataset.connectFee);

    let callInterval = null;
    let currentCallId = null;

    // Load balance on page load
    loadBalance();

    async function loadBalance() {
        try {
            const response = await fetch(`/api/balance/check.php?operator_id=${operatorId}`);
            const data = await response.json();

            if (data.success) {
                const balance = parseFloat(data.balance);
                const freeMinutes = parseInt(data.free_minutes);
                const totalMinutes = data.estimated_duration.total_minutes;

                document.querySelector('.balance-amount').textContent = `Balance: $${balance.toFixed(2)}`;

                if (freeMinutes > 0) {
                    document.getElementById('freeMinutes').textContent = `Free Minutes: ${freeMinutes} min`;
                    document.getElementById('freeMinutes').style.display = 'block';
                } else {
                    document.getElementById('freeMinutes').style.display = 'none';
                }

                document.getElementById('talkTime').textContent = `Est. Talk Time: ~${totalMinutes} minutes`;

                // Disable call button if can't afford
                const callBtn = document.getElementById('callBtn');
                if (!data.can_afford_call) {
                    callBtn.disabled = true;
                    callBtn.textContent = `Need $${data.minimum_required.toFixed(2)} to call`;
                }
            }
        } catch (error) {
            console.error('Failed to load balance:', error);
        }
    }

    window.initiateCall = async function() {
        const callBtn = document.getElementById('callBtn');
        callBtn.disabled = true;
        callBtn.textContent = 'Connecting...';

        try {
            const response = await fetch('/api/calls/initiate.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({operator_id: operatorId})
            });

            const data = await response.json();

            if (data.success) {
                currentCallId = data.call_id;

                // Hide call button, show active call UI
                document.getElementById('callActions').style.display = 'none';
                document.getElementById('activeCall').style.display = 'block';

                // Start call timer
                startCallTimer();

                alert(`Call initiated! You will receive a call on your phone shortly. Answer to be connected to ${data.operator}.`);
            } else {
                alert(data.error || 'Failed to initiate call');
                callBtn.disabled = false;
                callBtn.textContent = `📞 Call`;
            }
        } catch (error) {
            alert('Connection error. Please try again.');
            callBtn.disabled = false;
            callBtn.textContent = `📞 Call`;
        }
    };

    function startCallTimer() {
        let seconds = 0;

        callInterval = setInterval(async () => {
            seconds++;

            // Update timer display
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            document.getElementById('callTimer').textContent =
                `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

            // Poll for call status every 5 seconds
            if (seconds % 5 === 0 && currentCallId) {
                try {
                    const response = await fetch(`/api/calls/status.php?call_id=${currentCallId}`);
                    const status = await response.json();

                    if (status.success) {
                        document.getElementById('callCost').textContent =
                            `Cost: $${status.estimated_charges.toFixed(2)}`;

                        if (status.status === 'ended') {
                            endCall(status);
                        }
                    }
                } catch (error) {
                    console.error('Failed to get call status:', error);
                }
            }
        }, 1000);
    }

    function endCall(status) {
        if (callInterval) {
            clearInterval(callInterval);
            callInterval = null;
        }

        // Show call summary
        alert(`Call ended.\nDuration: ${status.duration_minutes} min\nTotal cost: $${status.estimated_charges.toFixed(2)}`);

        // Reset UI
        document.getElementById('activeCall').style.display = 'none';
        document.getElementById('callActions').style.display = 'flex';
        document.getElementById('callBtn').disabled = false;
        document.getElementById('callBtn').textContent = '📞 Call';

        currentCallId = null;

        // Reload balance
        loadBalance();
    }

    window.hangupCall = function() {
        // TODO: Implement hangup API
        if (confirm('End this call?')) {
            // For now, just stop the timer and show ended state
            if (callInterval) {
                clearInterval(callInterval);
            }
            alert('Call will end shortly.');
        }
    };
})();
</script>
