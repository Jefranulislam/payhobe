<?php
/**
 * PayHobe Admin API Documentation Template
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$base_url = rest_url('payhobe/v1/');
?>

<div class="wrap payhobe-api-docs">
    <h1><?php esc_html_e('PayHobe REST API Documentation', 'payhobe'); ?></h1>
    
    <p><?php esc_html_e('Use these REST API endpoints to integrate PayHobe with your Next.js dashboard or other applications.', 'payhobe'); ?></p>
    
    <!-- Base URL -->
    <div class="api-section">
        <h2><?php esc_html_e('Base URL', 'payhobe'); ?></h2>
        <code class="api-url"><?php echo esc_url($base_url); ?></code>
    </div>
    
    <!-- Authentication -->
    <div class="api-section">
        <h2><?php esc_html_e('Authentication', 'payhobe'); ?></h2>
        <p><?php esc_html_e('All API requests require authentication using a Bearer token.', 'payhobe'); ?></p>
        
        <h4><?php esc_html_e('Option 1: API Token (Recommended)', 'payhobe'); ?></h4>
        <p><?php esc_html_e('Generate an API token from Settings → API Access.', 'payhobe'); ?></p>
        <pre><code>Authorization: Bearer YOUR_API_TOKEN</code></pre>
        
        <h4><?php esc_html_e('Option 2: Login Endpoint', 'payhobe'); ?></h4>
        <pre><code>POST /auth/login
Content-Type: application/json

{
  "username": "your_username",
  "password": "your_password"
}</code></pre>
        
        <p><?php esc_html_e('Response:', 'payhobe'); ?></p>
        <pre><code>{
  "success": true,
  "token": "eyJ...",
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com"
  }
}</code></pre>
    </div>
    
    <!-- Endpoints -->
    <div class="api-section">
        <h2><?php esc_html_e('Endpoints', 'payhobe'); ?></h2>
        
        <!-- Payments -->
        <div class="api-endpoint-group">
            <h3>📦 <?php esc_html_e('Payments', 'payhobe'); ?></h3>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/payments</code>
                </div>
                <p><?php esc_html_e('List all payments with optional filters.', 'payhobe'); ?></p>
                <h5><?php esc_html_e('Query Parameters:', 'payhobe'); ?></h5>
                <table class="api-params">
                    <tr><td><code>status</code></td><td>pending, confirmed, failed</td></tr>
                    <tr><td><code>method</code></td><td>bkash, nagad, rocket, upay, bank</td></tr>
                    <tr><td><code>search</code></td><td>Search transaction ID</td></tr>
                    <tr><td><code>page</code></td><td>Page number (default: 1)</td></tr>
                    <tr><td><code>per_page</code></td><td>Items per page (default: 25)</td></tr>
                </table>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/payments/{id}</code>
                </div>
                <p><?php esc_html_e('Get a single payment by ID.', 'payhobe'); ?></p>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method post">POST</span>
                    <code>/payments</code>
                </div>
                <p><?php esc_html_e('Create a new payment.', 'payhobe'); ?></p>
                <h5><?php esc_html_e('Request Body:', 'payhobe'); ?></h5>
                <pre><code>{
  "method": "bkash",
  "amount": 1000.00,
  "transaction_id": "ABC123XYZ",
  "sender_number": "01712345678",
  "customer_name": "John Doe",
  "customer_email": "john@example.com"
}</code></pre>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method post">POST</span>
                    <code>/payments/{id}/verify</code>
                </div>
                <p><?php esc_html_e('Manually verify (confirm/reject) a payment.', 'payhobe'); ?></p>
                <pre><code>{
  "action": "confirm",  // or "reject"
  "notes": "Verified via bank statement"
}</code></pre>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/payments/stats</code>
                </div>
                <p><?php esc_html_e('Get payment statistics.', 'payhobe'); ?></p>
            </div>
        </div>
        
        <!-- Dashboard -->
        <div class="api-endpoint-group">
            <h3>📊 <?php esc_html_e('Dashboard', 'payhobe'); ?></h3>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/dashboard/overview</code>
                </div>
                <p><?php esc_html_e('Get dashboard overview with stats and recent payments.', 'payhobe'); ?></p>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/dashboard/stats</code>
                </div>
                <p><?php esc_html_e('Get detailed statistics.', 'payhobe'); ?></p>
                <h5><?php esc_html_e('Query Parameters:', 'payhobe'); ?></h5>
                <table class="api-params">
                    <tr><td><code>period</code></td><td>today, week, month, year (default: month)</td></tr>
                </table>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/dashboard/chart</code>
                </div>
                <p><?php esc_html_e('Get chart data for visualizations.', 'payhobe'); ?></p>
            </div>
        </div>
        
        <!-- Configuration -->
        <div class="api-endpoint-group">
            <h3>⚙️ <?php esc_html_e('Configuration', 'payhobe'); ?></h3>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/config</code>
                </div>
                <p><?php esc_html_e('Get all MFS configurations.', 'payhobe'); ?></p>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/config/{method}</code>
                </div>
                <p><?php esc_html_e('Get configuration for a specific method (bkash, nagad, etc.).', 'payhobe'); ?></p>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method post">POST</span>
                    <code>/config/{method}</code>
                </div>
                <p><?php esc_html_e('Update configuration for a method.', 'payhobe'); ?></p>
            </div>
        </div>
        
        <!-- SMS -->
        <div class="api-endpoint-group">
            <h3>📱 <?php esc_html_e('SMS Webhooks', 'payhobe'); ?></h3>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method post">POST</span>
                    <code>/sms/receive</code>
                </div>
                <p><?php esc_html_e('Webhook endpoint for receiving SMS from Android SMS Forwarder.', 'payhobe'); ?></p>
                <p class="note"><?php esc_html_e('This endpoint does not require authentication.', 'payhobe'); ?></p>
                <pre><code>{
  "from": "bKash",
  "message": "You have received Tk 1,000.00 from 01712345678. TrxID ABC123XYZ",
  "receivedAt": "2024-01-15T10:30:00Z"
}</code></pre>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method post">POST</span>
                    <code>/sms/twilio</code>
                </div>
                <p><?php esc_html_e('Webhook endpoint for Twilio SMS.', 'payhobe'); ?></p>
            </div>
            
            <div class="api-endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <code>/sms/logs</code>
                </div>
                <p><?php esc_html_e('Get SMS logs.', 'payhobe'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Error Responses -->
    <div class="api-section">
        <h2><?php esc_html_e('Error Responses', 'payhobe'); ?></h2>
        <p><?php esc_html_e('All errors follow this format:', 'payhobe'); ?></p>
        <pre><code>{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {
    "status": 403
  }
}</code></pre>
        
        <h4><?php esc_html_e('Common Error Codes:', 'payhobe'); ?></h4>
        <table class="api-params">
            <tr><td><code>401</code></td><td><?php esc_html_e('Unauthorized - Invalid or missing token', 'payhobe'); ?></td></tr>
            <tr><td><code>403</code></td><td><?php esc_html_e('Forbidden - Insufficient permissions', 'payhobe'); ?></td></tr>
            <tr><td><code>404</code></td><td><?php esc_html_e('Not Found - Resource does not exist', 'payhobe'); ?></td></tr>
            <tr><td><code>422</code></td><td><?php esc_html_e('Validation Error - Invalid input data', 'payhobe'); ?></td></tr>
        </table>
    </div>
    
    <!-- Code Examples -->
    <div class="api-section">
        <h2><?php esc_html_e('Code Examples', 'payhobe'); ?></h2>
        
        <h4>JavaScript (Fetch)</h4>
        <pre><code>const response = await fetch('<?php echo esc_url($base_url); ?>payments', {
  headers: {
    'Authorization': 'Bearer YOUR_API_TOKEN',
    'Content-Type': 'application/json'
  }
});
const data = await response.json();</code></pre>
        
        <h4>Next.js (with SWR)</h4>
        <pre><code>import useSWR from 'swr';

const fetcher = (url) => fetch(url, {
  headers: { 'Authorization': `Bearer ${process.env.NEXT_PUBLIC_API_TOKEN}` }
}).then(res => res.json());

export function usePayments() {
  return useSWR('<?php echo esc_url($base_url); ?>payments', fetcher);
}</code></pre>
        
        <h4>cURL</h4>
        <pre><code>curl -X GET "<?php echo esc_url($base_url); ?>payments" \
  -H "Authorization: Bearer YOUR_API_TOKEN"</code></pre>
    </div>
</div>

<style>
.payhobe-api-docs {
    max-width: 900px;
}
.api-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}
.api-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.api-url {
    display: block;
    padding: 15px;
    background: #f5f5f5;
    font-size: 14px;
}
.api-endpoint-group {
    margin: 20px 0;
}
.api-endpoint-group h3 {
    background: #f0f0f1;
    padding: 10px 15px;
    margin: 0;
    border-radius: 4px 4px 0 0;
}
.api-endpoint {
    padding: 15px;
    border: 1px solid #ddd;
    border-top: none;
}
.api-endpoint:last-child {
    border-radius: 0 0 4px 4px;
}
.endpoint-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}
.endpoint-header .method {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    color: #fff;
}
.method.get { background: #61affe; }
.method.post { background: #49cc90; }
.method.put { background: #fca130; }
.method.delete { background: #f93e3e; }
.api-params {
    width: 100%;
    border-collapse: collapse;
}
.api-params td {
    padding: 8px;
    border: 1px solid #ddd;
}
.api-params td:first-child {
    width: 150px;
    background: #f9f9f9;
}
pre {
    background: #23282d;
    color: #eee;
    padding: 15px;
    overflow-x: auto;
    border-radius: 4px;
}
pre code {
    background: none;
    padding: 0;
}
.note {
    background: #fff8e5;
    padding: 10px;
    border-left: 4px solid #ffb900;
}
</style>
