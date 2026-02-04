# PayHobe - Bangladeshi MFS Payment Gateway

**A headless WordPress plugin enabling Bangladeshi WooCommerce merchants to accept seamless online payments through leading Mobile Financial Services (MFS) such as bKash, Rocket, Nagad, Upay, as well as direct bank transfers.**

![PayHobe Banner](assets/images/banner.png)

---

## 🌟 Features

### Payment Methods
- **bKash** - Personal, Merchant, and Agent accounts
- **Nagad** - Personal, Merchant, and Agent accounts  
- **Rocket** - Personal and Agent accounts
- **Upay** - Personal and Merchant accounts
- **Bank Transfer** - Any Bangladeshi bank with screenshot proof

### Verification System
- **Auto-Verification** - SMS scraping matches Transaction IDs automatically
- **Manual Verification** - Admin can verify payments with one click
- **Batch Processing** - Process multiple pending payments at once
- **Timeout Handling** - Auto-cancel payments after configurable timeout

### Security
- **AES-256-CBC Encryption** - All sensitive data (account numbers, SMS) encrypted
- **JWT-based API Authentication** - Secure REST API access
- **Nonce Verification** - CSRF protection on all forms
- **Capability Checks** - Proper WordPress role permissions

### Dashboard
- **Next.js Frontend** - Modern React-based merchant dashboard
- **Real-time Stats** - Payment analytics and charts
- **SMS Logs** - View all received SMS for debugging
- **API Documentation** - Built-in API reference

---

## 📋 Requirements

### WordPress Plugin
- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- OpenSSL PHP extension
- MySQL 5.7 or higher

### Next.js Dashboard
- Node.js 18 or higher
- npm or yarn

---

## 🚀 Installation

### WordPress Plugin

1. **Upload the plugin**
   - Download the `wordpress-plugin` folder
   - Rename to `payhobe`
   - Upload to `/wp-content/plugins/`

2. **Activate the plugin**
   - Go to WordPress Admin → Plugins
   - Find "PayHobe" and click "Activate"

3. **Run the setup wizard**
   - After activation, you'll be redirected to the setup wizard
   - Follow the 4-step onboarding process

4. **Configure MFS accounts**
   - Go to PayHobe → MFS Configuration
   - Add your bKash, Nagad, Rocket, or Upay account details

5. **Set up SMS forwarding** (for auto-verification)
   - Install an SMS Forwarder app on your phone
   - Configure it to send SMS to your webhook URL:
     ```
     https://yoursite.com/wp-json/payhobe/v1/sms/receive
     ```

### Next.js Dashboard (Optional)

1. **Navigate to dashboard folder**
   ```bash
   cd dashboard
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env.local
   ```
   Edit `.env.local`:
   ```env
   NEXT_PUBLIC_API_URL=https://yoursite.com/wp-json/payhobe/v1
   NEXT_PUBLIC_API_TOKEN=your-api-token-here
   ```

4. **Run development server**
   ```bash
   npm run dev
   ```

5. **Build for production**
   ```bash
   npm run build
   npm start
   ```

---

## ⚙️ Configuration

### General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Business Name | Your business name shown to customers | Site title |
| Support Phone | Customer support number | — |
| Support Email | Customer support email | Admin email |
| Order Prefix | Prefix for payment references | PH |
| Instructions | Payment instructions shown to customers | — |

### Automation Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Auto-Verify | Automatically verify payments from SMS | Enabled |
| Payment Timeout | Minutes before pending payment expires | 30 |
| Amount Tolerance | Allowed variance in payment amount (৳) | 0 |

### Notification Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Admin Email | Email for payment notifications | Admin email |
| Notify on Pending | Email when new payment is pending | Enabled |
| Notify on Success | Email when payment is verified | Enabled |
| SMS Notifications | Send SMS to customers | Disabled |

---

## 🔌 REST API

### Authentication

All API requests require a Bearer token:

```http
Authorization: Bearer your-api-token
```

Generate tokens in WordPress Admin → PayHobe → Settings → API.

### Endpoints

#### Payments

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/payments` | List all payments |
| GET | `/payments/{id}` | Get single payment |
| POST | `/payments/{id}/verify` | Verify a payment |
| POST | `/payments/{id}/reject` | Reject a payment |

#### Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard/stats` | Get dashboard statistics |

#### MFS Configuration

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/mfs` | List MFS configurations |
| POST | `/mfs` | Create MFS configuration |
| PUT | `/mfs/{id}` | Update MFS configuration |
| DELETE | `/mfs/{id}` | Delete MFS configuration |

#### SMS

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sms` | List SMS logs |
| POST | `/sms/receive` | Webhook to receive SMS |

### Example Request

```bash
curl -X GET "https://yoursite.com/wp-json/payhobe/v1/payments" \
  -H "Authorization: Bearer your-api-token"
```

### Example Response

```json
{
  "payments": [
    {
      "payment_id": 123,
      "order_id": 456,
      "payment_method": "bkash",
      "amount": 1500.00,
      "transaction_id": "ABC123XYZ",
      "status": "pending",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "total": 1,
  "pages": 1
}
```

---

## 📱 SMS Forwarding Setup

### Recommended Apps

- **Tasker** (Android) - Most powerful, requires setup
- **SMS Forwarder** (Android) - Simple and free
- **MacroDroid** (Android) - Easy automation

### Webhook Format

Send POST requests to:
```
https://yoursite.com/wp-json/payhobe/v1/sms/receive
```

Required fields:
```json
{
  "sender": "bKash",
  "body": "You have received Tk 1,500.00 from 01712345678. Ref ABC123XYZ. Balance Tk 5,000.00",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Supported SMS Formats

PayHobe automatically parses SMS from:

- **bKash**: "You have received Tk X from Y. Ref Z."
- **Nagad**: "Tk X received from Y. TxnID: Z"
- **Rocket**: "Tk X received from Y. TrxID Z"
- **Upay**: "You received BDT X from Y. Trx ID: Z"

---

## 🛠️ Development

### Project Structure

```
payhobe/
├── wordpress-plugin/
│   ├── payhobe.php              # Main plugin file
│   ├── includes/
│   │   ├── class-payhobe.php    # Core class
│   │   ├── class-payhobe-activator.php
│   │   ├── class-payhobe-database.php
│   │   ├── class-payhobe-encryption.php
│   │   ├── class-payhobe-sms-parser.php
│   │   ├── class-payhobe-verification.php
│   │   ├── class-payhobe-payment-processor.php
│   │   ├── admin/
│   │   │   ├── class-payhobe-admin.php
│   │   │   └── class-payhobe-settings.php
│   │   ├── api/
│   │   │   ├── class-payhobe-rest-api.php
│   │   │   ├── class-payhobe-payments-controller.php
│   │   │   ├── class-payhobe-dashboard-controller.php
│   │   │   ├── class-payhobe-mfs-controller.php
│   │   │   └── class-payhobe-sms-controller.php
│   │   └── gateways/
│   │       ├── class-payhobe-gateway-bkash.php
│   │       ├── class-payhobe-gateway-nagad.php
│   │       ├── class-payhobe-gateway-rocket.php
│   │       ├── class-payhobe-gateway-upay.php
│   │       └── class-payhobe-gateway-bank.php
│   ├── templates/
│   │   ├── admin/
│   │   └── checkout/
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
│
└── dashboard/
    ├── src/
    │   ├── app/
    │   │   ├── dashboard/
    │   │   │   ├── page.tsx         # Dashboard home
    │   │   │   ├── payments/
    │   │   │   ├── sms/
    │   │   │   └── settings/
    │   │   └── login/
    │   ├── components/
    │   └── lib/
    └── public/
```

### Hooks & Filters

```php
// After payment is verified
do_action('payhobe_payment_verified', $payment_id, $payment);

// After payment is rejected
do_action('payhobe_payment_rejected', $payment_id, $payment);

// Before SMS is parsed
$sms_body = apply_filters('payhobe_pre_parse_sms', $sms_body);

// Filter payment instructions
$instructions = apply_filters('payhobe_payment_instructions', $instructions, $method);
```

---

## 🧪 Testing

### Test Mode

Enable test mode in settings to:
- Skip actual payment verification
- Use test transaction IDs
- Log all operations

### Test Transaction IDs

Use these in test mode:
- `TEST123SUCCESS` - Auto-verifies
- `TEST123PENDING` - Stays pending
- `TEST123FAIL` - Auto-rejects

---

## 📄 License

GPL v2 or later

---

## 🤝 Support

- **Documentation**: [View Docs](https://payhobe.com/docs)
- **GitHub Issues**: [Report Bug](https://github.com/your-repo/payhobe/issues)
- **Email**: support@payhobe.com

---

## 🙏 Credits

- Built for Bangladeshi WooCommerce merchants
- SMS parsing patterns from community research
- Icons from Heroicons

---

Made with ❤️ in Bangladesh
