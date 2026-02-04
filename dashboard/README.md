# PayHobe Dashboard

Next.js frontend dashboard for PayHobe - Bangladeshi MFS Payment Gateway.

## Quick Start

### Prerequisites

- Node.js 18+
- PayHobe WordPress plugin installed and configured
- API token generated from WordPress admin

### Installation

```bash
# Install dependencies
npm install

# Copy environment file
cp .env.example .env.local

# Edit .env.local with your API details
```

### Configuration

Edit `.env.local`:

```env
NEXT_PUBLIC_API_URL=https://yoursite.com/wp-json/payhobe/v1
NEXT_PUBLIC_API_TOKEN=your-api-token-here
```

### Development

```bash
npm run dev
```

Open [http://localhost:3000](http://localhost:3000)

### Production Build

```bash
npm run build
npm start
```

## Features

- **Dashboard** - Real-time payment statistics and charts
- **Payments** - List, search, filter, verify/reject payments
- **SMS Logs** - View received SMS messages
- **Settings** - Manage MFS accounts

## Tech Stack

- Next.js 14
- React 18
- TypeScript
- TailwindCSS
- React Query
- Chart.js
- React Hot Toast

## Project Structure

```
src/
├── app/
│   ├── dashboard/
│   │   ├── page.tsx         # Dashboard home
│   │   ├── layout.tsx       # Dashboard layout with sidebar
│   │   ├── payments/
│   │   │   ├── page.tsx     # Payments list
│   │   │   └── [id]/page.tsx # Payment detail
│   │   ├── sms/page.tsx     # SMS logs
│   │   └── settings/page.tsx # Settings
│   ├── login/page.tsx       # Login page
│   └── page.tsx             # Root redirect
├── components/
│   ├── Sidebar.tsx          # Navigation sidebar
│   ├── Badge.tsx            # Status and method badges
│   ├── StatsCard.tsx        # Statistics card
│   ├── PaymentsTable.tsx    # Payments table component
│   └── Charts.tsx           # Chart components
└── lib/
    ├── api.ts               # API client and types
    ├── auth.tsx             # Authentication context
    └── utils.ts             # Utility functions
```

## API Integration

The dashboard connects to the PayHobe WordPress REST API:

- `GET /dashboard/stats` - Dashboard statistics
- `GET /payments` - List payments
- `GET /payments/{id}` - Single payment
- `POST /payments/{id}/verify` - Verify payment
- `POST /payments/{id}/reject` - Reject payment
- `GET /sms` - SMS logs
- `GET /mfs` - MFS configurations
- `POST /mfs` - Create MFS config
- `PUT /mfs/{id}` - Update MFS config
- `DELETE /mfs/{id}` - Delete MFS config

## Deployment

### Vercel (Recommended)

```bash
npm i -g vercel
vercel
```

### Docker

```dockerfile
FROM node:18-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build
EXPOSE 3000
CMD ["npm", "start"]
```

### Static Export

```bash
npm run build
# Deploy the .next folder
```

## License

GPL v2 or later
